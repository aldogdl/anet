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
    public function __construct(WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths)
    {
        $trackFile = new TrackFileCot($message,
            ['tracking' => $paths['tracking'], 'trackeds' => $paths['trackeds']]
        );

        if($trackFile->isAtendido) {
            // Al entrar aqui es que no se encontró el item respondido por un boton entre
            // la lista del TrackFile pero es necesario evaluar ciertas cosas...

            // 1.- Hay mas items que atender??
            if($trackFile->hasItems) {
                // Si hay mas items pero necesitamos ver si el boton que se apreto es diferente a track
                // si es track es que quiere cotizar la que ya habia atendido y eso no esta permitido.
                if($message->subEvento == 'sfto') {
                    // TODO avisar al cotizador con un mensaje.
                    // file_put_contents('wa_rechazada.json', json_encode($message->message));
                    // return;
                }
            }
        }

        $itemFetchToSent = [];
        if($message->subEvento == 'ntg' || $message->subEvento == 'ntga') {
            $itemFetchToSent = $trackFile->fetchItemToSent();
        }
        
        $template = [];
        $typeMsgToSent = 'text';
        $trackFile->fSys->setPathBase($paths['chat']);
        
        /// El boton disparador fue un ntg|ntga y se encontró un item a enviar
        if(count($itemFetchToSent) > 0) {

            //Buscamos para ver si existe el mensaje del item prefabricado.
            $trackFile->fSys->setPathBase($paths['prodTrack']);
            $template = $trackFile->fSys->getContent($itemFetchToSent['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $template = $template['message'];
                }
                $typeMsgToSent = $template['type'];
                $template = $template[$typeMsgToSent];
            }

        }else{

            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $trackFile->fSys->setPathBase($paths['waTemplates']);
            $template = $trackFile->fSys->getContent($message->subEvento.'.json');
            if(strlen($message->context) > 0) {
                $template['context'] = $message->context;
                $trackFile->itemCurrentResponsed['version']   = $trackFile->trackFile['version'];
                $trackFile->itemCurrentResponsed['wamid_cot'] = $message->context;
            }
            
            // Si el mensaje es el inicio de una cotizacion creamos un archivo especial
            if($message->subEvento == 'sfto') {
                $trackFile->fSys->setPathBase($paths['cotProgres']);
                $trackFile->fSys->setContent($message->from.'.json', $trackFile->itemCurrentResponsed);
            }
        }
        
        $sended = [];
        $entroToSended = false;
        $trackFile->fSys->setPathBase($paths['chat']);
        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
        if(count($template) > 0) {

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
            'trackfile'=> $trackFile->trackFile['version']
        ]);
    }

}
