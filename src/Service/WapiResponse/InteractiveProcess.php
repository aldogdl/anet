<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WapiResponse\FsysProcess;

class InteractiveProcess
{

    public String $hasErr = '';

    /** 
     * Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
     * que se necesita enviar como respuesta inmendiata a este.
     * Este dato debe de colacarce en la propiedad subEvento del Objeto WaMsgMdl creado
     * en la clase: @see App\Service\WapiRequest\ExtractMessage()
    */
    public function __construct(WaMsgMdl $message, array $paths, WebHook $wh, WrapHttp $wapiHttp)
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

        $filetrack = [];
        $itemFetchToSent = [];
        if($message->subEvento == 'ntg' || $message->subEvento == 'ntga') {
            $itemFetchToSent = $trackFile->fetchItemToSent();
        }else{
            // Si se respondio con un Cotizar ahora, solo guardamos el fileTrack
            $trackFile->update();
        }

        $filetrack = $trackFile->trackFile['version'];
        $trackFile = null;
        
        $template = [];
        $typeMsgToSent = 'text';
        $fSys = new FsysProcess($paths['chat']);
        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);

        /// El boton disparador fue un ntg|ntga y se encontró un item a enviar
        if(count($itemFetchToSent) > 0) {
            //Buscamos para ver si existe el mensaje del item prefabricado.
            $fSys->setPathBase($paths['prodTrack']);
            $template = $fSys->getContent($itemFetchToSent['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $template = $template['message'];
                }
                $typeMsgToSent = $template['type'];
                $template = $template[$typeMsgToSent];
            }
        }else{
            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $fSys->setPathBase($paths['waTemplates']);
            $template = $fSys->getContent($message->subEvento.'.json');
        }
        
        $entroToSended = false;
        $fSys->setPathBase($paths['chat']);
        if(count($template) > 0) {

            $conm->setBody($typeMsgToSent, $template);
            $result = $wapiHttp->send($conm);
            if($result['statuscode'] != 200) {
                $wh->sendMy('wa-wh', 'notSave', $result);
                return;
            }
            $idItem = '0';
            if(array_key_exists('action', $template)) {
                if(array_key_exists('buttons', $template['action'])) {
                    $idItem = $template['action']['buttons'][0]['reply']['id'];
                    $partes = explode('_', $idItem);
                    $idItem = $partes[1];
                }
            }
            $conm->bodyRaw = ['text' => $template['body'], 'idItem' => $idItem];
            $sended = $conm->setIdToMsgSended($message, $result);
            $entroToSended = true;
        }

        $fSys->dumpIn($message->toArray());
        if($entroToSended) {
            $fSys->dumpIn($sended->toArray());
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended->toArray(),
            'trackfile'=> $filetrack
        ]);

    }

}
