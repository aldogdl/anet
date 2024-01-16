<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class InteractiveProcess
{

    public String $hasErr = '';
    /** 
     * Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
     * que se necesita enviar como respuesta inmendiata a este.
     * Este dato debe de colacarce en la propiedad subEvento del Objeto WaMsgMdl creado
     * en la clase: @see App\Service\WapiRequest\ExtractMessage()
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){

        $itemFetchToSent = [];
        if($message->subEvento == 'ntg' || $message->subEvento == 'ntga') {
            $trackFile = new TrackFileCot($message, $paths);
            $itemFetchToSent = $trackFile->fetchItemToSent();
        }
        
        $template = [];
        $typeMsgToSent = 'text';
        /// El boton disparador fue un ntg|ntga y se encontró un item a enviar
        if(count($itemFetchToSent) > 0) {

            //Buscamos para ver si existe el mensaje del item prefabricado.
            $trackFile->fSys->setPathBase($paths['prodTrack']);
            $template = $trackFile->fSys->getContent($itemFetchToSent['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $template = $template['message'];
                }
            }

        }else{

            $respRapida = '';
            if(mb_strpos($message->subEvento, '.') !== false) {
                $partes = explode('.', $message->subEvento);
                $message->subEvento = $partes[0];
                $respRapida = $partes[1];
                
                $saveProcess = false;
                if($message->subEvento == 'sdta' && $cotProgress['current'] == 'sfto') {
                    // Estamos en fotos y preciono un boton de opcion
                    if($respRapida == 'fton') {
                        $cotProgress['current'] = 'sdta';
                        $cotProgress['next'] = 'scto';
                        $cotProgress['track']['fotos'] = [];
                        $saveProcess = true;
                    }
                }
                
                if($message->subEvento == 'scto' && $cotProgress['current'] == 'sdta') {
                    // Estamos en detalles y preciono un boton de opcion
                    if($respRapida == 'uso') {
                        $cotProgress['current'] = 'scto';
                        $cotProgress['next'] = 'sgrx';
                        $cotProgress['track']['detalles'] = 'La pieza cuenta con Detalles de Uso';
                        $saveProcess = true;
                    }
                }
                if($saveProcess) {
                    $trackFile->fSys->setPathBase($paths['cotProgres']);
                    $trackFile->fSys->setContent($message->from.'.json', $cotProgress);
                }
            }

            $trackFile->fSys->setPathBase($paths['waTemplates']);
            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $template = $trackFile->fSys->getContent($message->subEvento.'.json');
                        
            // Buscamos si contiene AnetLanguage para decodificar
            $deco = new DecodeTemplate($cotProgress);
            $template = $deco->decode($template);

            $contexto = '';
            if(array_key_exists('wamid_cot', $cotProgress)) {
                $contexto = $cotProgress['wamid_cot'];
            }else{
                if(strlen($message->context) > 0) {
                    $contexto = $message->context;
                }
            }
            if(strlen($contexto) > 0) {
                $template['context'] = $contexto;
                // $trackFile->itemCurrentResponsed['version']   = $trackFile->trackFile['version'];
                $trackFile->itemCurrentResponsed['wamid_cot'] = $contexto;
            }
            
            // Si el mensaje es el inicio de una cotizacion creamos un archivo especial
            if($message->subEvento == 'sfto') {
                $trackFile->fSys->setPathBase($paths['cotProgres']);
                if(!array_key_exists('idCot', $trackFile->itemCurrentResponsed['track'])) {
                    $trackFile->itemCurrentResponsed['track'] = ['idCot' => time()];
                }
                $trackFile->fSys->setContent($message->from.'.json', $trackFile->itemCurrentResponsed);
            }
        }
        
        $sended = [];
        $entroToSended = false;
        $trackFile->fSys->setPathBase($paths['chat']);
        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
        if(count($template) > 0) {

            $typeMsgToSent = $template['type'];
            $conm->setBody($typeMsgToSent, $template);
            $result = $wapiHttp->send($conm);
            
            if($result['statuscode'] != 200) {
                $wh->sendMy('wa-wh', 'notSave', $result);
                return;
            }

            // Se responde con un mensaje al cotizador en respuesta a su accion.
            // Si el mensaje fue una nueva solicitud de cotizacion procesada por el estanque
            // Extraemos el IdItem del producto para que EventCore reaccione a este.
            $idItem = '0';
            $template = $template[$typeMsgToSent];

            if(array_key_exists('action', $template)) {
                if(array_key_exists('buttons', $template['action'])) {
                    $idItem = $template['action']['buttons'][0]['reply']['id'];
                    $partes = explode('_', $idItem);
                    $idItem = $partes[1];
                }
                $conm->bodyRaw = ['body' => $template['body'], 'idItem' => $idItem];
            }else{
                $conm->bodyRaw = $template['body'];
            }

            $objMdl = $conm->setIdToMsgSended($message, $result);
            $sended = $objMdl->toArray();
            $entroToSended = true;
        }

        $trackFile->fSys->dumpIn($message->toArray());
        if($entroToSended) {
            $trackFile->fSys->dumpIn($sended);
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended,
            'trackfile'=> (count($cotProgress) == 0)
                ? $trackFile->itemCurrentResponsed['version']
                : $cotProgress
        ]);
    }

}
