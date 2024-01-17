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
        'scto' => ['current' => 'sgrx', 'next' => 'sfto'],
    ];

    /** */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){

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
        $this->cotProgress['track'][$campo] = $message->message;

        // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
        $fSys = new FsysProcess($paths['cotProgres']);
        $fSys->setContent($message->from.'.json', $this->cotProgress);

        // Respondemos inmediatamente a este con el mensaje adecuado
        $fSys->setPathBase($paths['waTemplates']);
        $template = $fSys->getContent($this->cotProgress['current'].'.json');
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

        if($campo == 'precio') {
            $this->fetchBait($message, $wh, $wapiHttp, $fSys, $paths);
        }
    }

    /** */
    private function fetchBait(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, FsysProcess $fSys, array $paths
    ){
        $message->message = [
            'idItem' => $this->cotProgress['idItem'],
            'body' => $message->message
        ];

        $tf = new TrackFileCot($message, $paths);
        
        $itemBaitToSent = $tf->lookForBait();
        if(count($itemBaitToSent) > 0) {

            $this->cotProgress = $tf->cotProcess;
            //Buscamos para ver si existe el mensaje del item prefabricado.
            $tf->fSys->setPathBase($paths['prodTrack']);
            $template = $tf->fSys->getContent($itemBaitToSent['idItem'].'_track.json');
            
            if(count($template) > 0) {

                if(array_key_exists('message', $template)) {
                    $template = $template['message'];
                }
                $this->entroToSended = false;
                $sended = $this->sentMsg($template, $message, $wh, $wapiHttp, $paths['tkwaconm']);
                $fSys->setPathBase($paths['chat']);
                if($this->entroToSended) {
                    $fSys->dumpIn($sended);
                }
            }
        }
        
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => ['type' => 'interactive', 'estanque' => 'fetch'],
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'trackfile'=> $this->cotProgress
        ]);
    }

    /** */
    private function sentMsg(
        array $template, WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, String $pathCom
    ): array {

        $typeMsgToSent = $template['type'];
        $conm = new ConmutadorWa($message->from, $pathCom);
        if(count($template) > 0) {
            
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

        $conm = null;
        return [];
    }
}
