<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotTextProcess
{
    private bool $entroToSended = false;
    private array $cotProgress;
    private array $msgs = [
        'sdta' => ['current' => 'scto', 'next' => 'sgrx'],
        'scto' => ['current' => 'sgrx', 'next' => 'sok'],
    ];

    /** */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){
        $versionTrackFile = -1;
        $current = $cotProgress['current'];
        $campo = ($current == 'sdta') ? 'detalles' : 'precio';
        $message->subEvento = $current;
        $this->cotProgress = $cotProgress;
        $cotProgress = [];
        $sended = [];
        $this->entroToSended = false;

        if(!array_key_exists($current, $this->msgs)) {
            return;
        }
        
        // Actualizar el trackFile para el siguiente mensaje y contenido de cotizacion
        $this->cotProgress['current'] = $this->msgs[$current]['current'];
        $this->cotProgress['next']    = $this->msgs[$current]['next'];
        if(array_key_exists('body', $message->message)) {
            $this->cotProgress['track'][$campo] = $message->message['body'];
        }else{
            $this->cotProgress['track'][$campo] = $message->message;
        }

        // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
        $pathInit = ($this->cotProgress['current'] == 'sgrx') ? 'waTemplates' : 'cotProgres';
        $fSys = new FsysProcess($paths[$pathInit]);
        if($pathInit == 'cotProgres') {
            $fSys->setContent($message->from.'.json', $this->cotProgress);
            $fSys->setPathBase($paths['waTemplates']);
        }

        // Respondemos inmediatamente a este con el mensaje adecuado
        $template = $fSys->getContent($this->cotProgress['current'].'.json');
        if(count($template) == 0) {
            return;
        }
        // Buscamos si contiene AnetLanguage para decodificar
        $deco = new DecodeTemplate($cotProgress);
        $template = $deco->decode($template);
        if(array_key_exists('wamid_cot', $this->cotProgress)) {
            $template['context'] = $this->cotProgress['wamid_cot'];
        }
        $sended = $this->sentMsg($template, $message, $wh, $wapiHttp, $paths['tkwaconm']);

        $recibido = $message->toArray();
        $fSys->setPathBase($paths['chat']);
        $fSys->dumpIn($recibido);

        if($this->entroToSended) {
            $fSys->dumpIn($sended);
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $recibido,
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'trackfile'=> $this->cotProgress
        ]);

        if($this->cotProgress['current'] == 'sgrx') {
            $this->fetchBait($message, $wh, $wapiHttp, $fSys, $paths);
        }
    }

    /** */
    private function fetchBait(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, FsysProcess $fSys, array $paths
    ){
        $this->entroToSended = false;
        $versionTrackFile = -1;
        $message->message = [
            'idItem' => $this->cotProgress['idItem'],
            'body' => $message->message
        ];

        $tf = new TrackFileCot($message, $paths, $fSys);
        // Lo primero es eliminar del estanque la solicitud cotizada y enviarlo a tracked
        $tf->finOfCotizacion();
        if(count($tf->cotProcess) == 0) {
            return;
        }
        $versionTrackFile = $tf->versionFileTrack;
        $this->cotProgress = $tf->cotProcess;
        //Buscamos para ver si existe el mensaje del item prefabricado.
        $tf->fSys->setPathBase($paths['prodTrack']);
        $template = $tf->fSys->getContent($this->cotProgress['idItem'].'_track.json');
        if(count($template) == 0) {
            return;
        }
        if(!array_key_exists('message', $template)) {
            return;
        }
        
        $template = $template['message'];
        $sended = $this->sentMsg($template, $message, $wh, $wapiHttp, $paths['tkwaconm']);
        if($this->entroToSended) {
            $fSys->setPathBase($paths['chat']);
            $fSys->dumpIn($sended);
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => ['type' => 'interactive', 'estanque' => 'fetch'],
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'trackfile'=> (count($this->cotProgress) == 0) ? $versionTrackFile : $this->cotProgress
        ]);
    }

    /** */
    private function sentMsg(
        array $template, WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, String $pathCom
    ): array {

        $typeMsgToSent = $template['type'];
        $conm = new ConmutadorWa($message->from, $pathCom);
        $conm->setBody($typeMsgToSent, $template);
        $result = $wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $this->entroToSended = false;
            $wh->sendMy('wa-wh', 'notSave', $result);
            return [];
        }

        $template = $template[$typeMsgToSent];
        // Extraemos el IdItem del mensaje que se va a enviar al cotizador cuando se
        // responde con otro mensaje interactivo
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
        $conm = null;
        $this->entroToSended = true;
        return $objMdl->toArray();
    }
}
