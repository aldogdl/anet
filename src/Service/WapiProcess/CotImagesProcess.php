<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotImagesProcess
{
    private WebHook $wh;
    private WrapHttp $wapiHttp;
    private WaMsgMdl $msg;
    private array $paths;
    private array $cotProgress;

    /** 
     * Esperamos la llegada de las imagenes departe del cotizador
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){

        $this->wh          = $wh;
        $this->wapiHttp    = $wapiHttp;
        $this->msg         = $message;
        $this->cotProgress = $cotProgress;
        $this->paths       = $paths;
        $cotProgress       = [];
    }

    /** */
    public function exe(): void
    {
        $fotos = [];
        $this->msg->subEvento = 'sfto';
        
        if(array_key_exists('fotos', $this->cotProgress['track'])) {
            $fotos = $this->cotProgress['track']['fotos'];
        }

        $fto = [];
        if(array_key_exists('body', $this->msg->message)) {
            if(!in_array($this->msg->message['body']['id'], $fotos)) {
                $fto = [
                    'id'  => $this->msg->message['body']['id'],
                    'cap' => (array_key_exists('caption', $this->msg->message['body']))
                        ? $this->msg->message['body']['caption']
                        : '',
                ];
            }
        }else{
            if(!in_array($this->msg->message['id'], $fotos)) {
                $fto = [
                    'id'  => $this->msg->message['id'],
                    'cap' => (array_key_exists('caption', $this->msg->message))
                        ? $this->msg->message['caption']
                        : '',
                ];
            }
        }

        $fotos[] = $fto;
        $this->cotProgress['track']['fotos'] = $fotos;
        
        // Si current es sdta es que estamos solicitando los detalles y siguen llegando fotos
        // por lo tanto guardamos las fotos inmediatamente en el archivo cotProgress
        $sender = new SentTemplate(
            $this->msg, $this->wh, $this->wapiHttp, $this->paths, $this->cotProgress
        );
        if($this->cotProgress['current'] == 'sdta') {
            $sender->subEvento = 'sdta';
            $sender->saveCotProgress();
        }

        if($this->cotProgress['current'] == 'sfto') {
            
            $this->cotProgress['current'] = 'sdta';
            $this->cotProgress['next'] = 'scto';
            
            // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
            $sender->subEvento = $this->cotProgress['current'];
            $sender->updateCotProgress($this->cotProgress);
            $sender->saveCotProgress();
            $sender->getTemplate();
            $sender->sent();
            $filename = $this->cotProgress['waId'].'_0_'.time().'_.imgs';
            file_put_contents($this->paths['cotProgres'].'/'.$filename, '');
        }
    }

}
