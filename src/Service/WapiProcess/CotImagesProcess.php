<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotImagesProcess
{

    public String $hasErr = '';
    private array $cotProgress;
    private $permitidas = ['jpeg', 'jpg', 'webp', 'png'];

    /** 
     * 
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){
        if(!in_array($message->status, $this->permitidas)) {
            // TODO enviar mensaje al cliente de no permitida la extencion
            return;
        }

        $this->cotProgress = $cotProgress;
        $cotProgress = [];

        if(count($this->cotProgress) > 0) {
            if(array_key_exists('item', $this->cotProgress)) {
                $message->message['idItem'] = $this->cotProgress['item'];
            }
        }
        $trackFile = new TrackFileCot($message,
            ['tracking' => $paths['tracking'], 'trackeds' => $paths['trackeds']]
        );
        if(count($trackFile->itemCurrentResponsed) == 0) {
            // TODO Avisar al cliente que no se encontró la solicitud, que cotize otra
            return;
        }

        // Actualizar el trackFile para el siguiente mensaje y contenido de cotizacion
        $trackFile->itemCurrentResponsed['current'] = 'sdta';
        $trackFile->itemCurrentResponsed['next'] = 'scto';

        $sended = [];
        $entroToSended = false;
        $trackFile->fSys->setPathBase($paths['waTemplates']);
        
        if($this->cotProgress['espero'] == 'images') {
            
            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $template = $trackFile->fSys->getContent($trackFile->itemCurrentResponsed['current'].'.json');
            
            // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
            $this->cotProgress['espero'] = 'detalles';
            $trackFile->fSys->setPathBase($paths['cotProgres']);
            $trackFile->fSys->setContent($message->from.'.json', $this->cotProgress);
            // Revisamos si existe el id del contexto de la cotizacion para agregarlo al msg de respuesta
            if(array_key_exists('cot', $this->cotProgress)) {
                $template['context'] = $this->cotProgress['cot'];
            }

            $typeMsgToSent = 'text';
            $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
            if(count($template) > 0) {

                $conm->setBody($typeMsgToSent, $template);
                $result = $wapiHttp->send($conm);
                if($result['statuscode'] != 200) {
                    $wh->sendMy('wa-wh', 'notSave', $result);
                    return;
                }

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

        $fotos = [];
        if(array_key_exists('fotos', $trackFile->itemCurrentResponsed['track'])) {
            $fotos = $trackFile->itemCurrentResponsed['track']['fotos'];
        }
        
        $fotos[] = $message->message['id'];
        $trackFile->itemCurrentResponsed['track']['fotos'] = $fotos;
        
        $recibido = $message->toArray();
        $trackFile->fSys->setPathBase($paths['chat']);
        $trackFile->fSys->dumpIn($recibido);
        if($entroToSended) {
            $trackFile->fSys->dumpIn($sended);
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $recibido,
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'trackfile'=> $trackFile->itemCurrentResponsed
        ]);
    }

}
