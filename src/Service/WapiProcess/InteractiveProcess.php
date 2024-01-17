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
        $itemBaitToSent = [];
        $tf = new TrackFileCot($message, $paths);
        $createCotProgress = false;

        if($message->subEvento == 'ntg' || $message->subEvento == 'ntga') {
            // Buscamo una carnada en el estanque
            $itemBaitToSent = $tf->lookForBait();
        }

        $template = [];
        /// El boton disparador fue un ntg|ntga y se encontró una carnada para enviar
        if(count($itemBaitToSent) > 0) {

            //Buscamos para ver si existe el mensaje del item prefabricado.
            $tf->fSys->setPathBase($paths['prodTrack']);
            $template = $tf->fSys->getContent($itemBaitToSent['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $template = $template['message'];
                }
            }

        }else{

            $saveCotProcess = false;
            if(count($cotProgress) == 0) {
                // No hay actualmente ninguna cotizacion en progreso, por lo tanto buscamos
                // el item entre el TrackFile
                $tf->build();
                if(count($tf->cotProcess) == 0) {
                    // TODO La solicitud ya no esta disponible MSG al cliente
                    return;
                }
                $cotProgress = $tf->cotProcess;
            }

            if(array_key_exists('track', $cotProgress)) {
                if(!array_key_exists('idCot', $cotProgress['track'])) {
                    $createCotProgress = true;
                }
            }else{
                $createCotProgress = true;
            }

            if($createCotProgress && $message->subEvento == 'sfto') {
                // Si no hay ningun archivo que indica cotizacion en progreso lo creamos
                $cotProgress['sended'] = round(microtime(true) * 1000);
                $cotProgress['track'] = ['idCot' => time()];
                $saveCotProcess = true;
            }

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
                        $cotProgress['next']    = 'sgrx';
                        $cotProgress['track']['detalles'] = 'La pieza cuenta con Detalles de Uso';
                        $saveProcess = true;
                    }
                }
                if($saveProcess) {
                    $tf->fSys->setPathBase($paths['cotProgres']);
                    $tf->fSys->setContent($message->from.'.json', $cotProgress);
                }
            }

            $tf->fSys->setPathBase($paths['waTemplates']);
            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $template = $tf->fSys->getContent($message->subEvento.'.json');
                        
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
                $template['context']      = $contexto;
                $cotProgress['wamid_cot'] = $contexto;
            }
            
            // Si el mensaje es el inicio de una cotizacion creamos un archivo especial
            if($saveCotProcess) {
                $tf->fSys->setPathBase($paths['cotProgres']);
                $tf->fSys->setContent($message->from.'.json', $cotProgress);
            }
        }
        
        $sended = [];
        $entroToSended = false;
        $typeMsgToSent = 'text';

        if(count($template) > 0) {
            
            $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);

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
                $conm->bodyRaw = ['idItem' => $idItem, 'body' => $template['body']];
            }else{
                $conm->bodyRaw = $template['body'];
            }

            $objMdl = $conm->setIdToMsgSended($message, $result);
            $sended = $objMdl->toArray();
            $entroToSended = true;
        }

        $tf->fSys->setPathBase($paths['chat']);
        $tf->fSys->dumpIn($message->toArray());
        if($entroToSended) {
            $tf->fSys->dumpIn($sended);
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended,
            'trackfile'=> $cotProgress
        ]);
    }

}
