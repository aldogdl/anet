<?php

namespace App\Service\WapiProcess;

use App\Entity\EstanqueReturn;
use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotImagesProcess
{
    private array $cotProgress;

    /** 
     * Esperamos la llegada de las imagenes departe del cotizador
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){

        $this->cotProgress = $cotProgress;
        $cotProgress = [];

        $fotos = [];
        $sended = [];
        $entroToSended = false;
        $message->subEvento = 'sfto';
        
        if(array_key_exists('fotos', $this->cotProgress['track'])) {
            $fotos = $this->cotProgress['track']['fotos'];
        }

        if(array_key_exists('body', $message->message)) {
            if(!in_array($message->message['body']['id'], $fotos)) {
                $fotos[] = $message->message['body']['id'];
            }
        }else{
            if(!in_array($message->message['id'], $fotos)) {
                $fotos[] = $message->message['id'];
            }
        }
        $this->cotProgress['track']['fotos'] = $fotos;
        
        $current = $this->cotProgress['current'];
        
        $fSys = new FsysProcess($paths['cotProgres']);
        // Si current es sdta es que estamos solicitando los detalles y siguen llegando fotos
        // por lo tanto guardamos las fotos inmediatamente en el archivo cotProgress
        if($current == 'sdta') {
            $fSys->setContent($message->from.'.json', $this->cotProgress);
        }

        if($current == 'sfto') {
            
            $this->cotProgress['current'] = 'sdta';
            $this->cotProgress['next'] = 'scto';
            // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
            $fSys->setContent($message->from.'.json', $this->cotProgress);
            
            // Respondemos inmediatamente a este boton interactivo con el mensaje adecuado
            $fSys->setPathBase($paths['waTemplates']);
            $template = $fSys->getContent($this->cotProgress['current'].'.json');
            
            // Buscamos si contiene AnetLanguage para decodificar
            $deco = new DecodeTemplate($this->cotProgress);
            $template = $deco->decode($template);

            // Revisamos si existe el id del contexto de la cotizacion para agregarlo al msg de respuesta
            if(array_key_exists('wamid_cot', $this->cotProgress)) {
                $template['context'] = $this->cotProgress['wamid_cot'];
            }

            $typeMsgToSent = 'text';
            $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
            if(count($template) > 0) {
                
                $typeMsgToSent = $template['type'];
                $conm->setBody($typeMsgToSent, $template);
                $result = $wapiHttp->send($conm);
                if($result['statuscode'] != 200) {
                    $wh->sendMy('wa-wh', 'notSave', $result);
                    return;
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
                $sended = $objMdl->toArray();
                $entroToSended = true;
            }
        }

        $recibido = $message->toArray();
        $fSys->setPathBase($paths['chat']);
        $fSys->dumpIn($recibido);
        if($entroToSended) {
            $fSys->dumpIn($sended);
        }
        
        $result = new EstanqueReturn([], 'less', true, $this->cotProgress);
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $recibido,
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'estanque' => $result
        ]);
    }

}
