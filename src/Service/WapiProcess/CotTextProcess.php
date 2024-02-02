<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotTextProcess
{
    private WebHook $wh;
    private WrapHttp $wapiHttp;
    private WaMsgMdl $msg;
    private SentTemplate $sender;
    private array $paths;
    private array $cotProgress;
    private array $msgsNames = [
        'sdta' => ['current' => 'scto', 'next' => 'sgrx'],
        'scto' => ['current' => 'sgrx', 'next' => 'sok'],
    ];

    /** */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){

        $this->cotProgress = $cotProgress;
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
        $current = $this->cotProgress['current'];
        $campo = ($current == 'sdta') ? 'detalles' : 'precio';
        $this->msg->subEvento = $current;
        if(!array_key_exists($current, $this->msgsNames)) {
            return;
        }
        
        // Actualizar el trackFile para el siguiente mensaje y contenido de cotizacion
        $this->cotProgress['current'] = $this->msgsNames[$current]['current'];
        $this->cotProgress['next']    = $this->msgsNames[$current]['next'];
        if(array_key_exists('body', $this->msg->message)) {
            $this->cotProgress['track'][$campo] = $this->msg->message['body'];
        }else{
            $this->cotProgress['track'][$campo] = $this->msg->message;
        }

        $this->sender = new SentTemplate(
            $this->msg, $this->wh, $this->wapiHttp, $this->paths, $this->cotProgress
        );
        
        // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
        $pathInit = ($this->cotProgress['current'] == 'sgrx') ? 'waTemplates' : 'cotProgres';
        if($pathInit == 'cotProgres') {
            $this->sender->saveCotProgress();
        }
        
        // Respondemos inmediatamente a este con el mensaje adecuado
        $this->sender->subEvento = $current;
        $this->sender->updateCotProgress($this->cotProgress);
        $this->sender->getTemplate();
        $this->sender->sent();

        if($this->cotProgress['current'] == 'sgrx') {
            $this->fetchBait();
        }
    }

    /** 
     * Al haber terminado de cotizar revisamos si cuenta con una carnada para enviarcela inmediatamente
    */
    private function fetchBait(): void
    {
        if(!array_key_exists('idItem', $this->msg->message)) {
            $this->msg->message = [
                'idItem' => $this->cotProgress['idItem'],
                'body' => $this->msg->message
            ];
        }

        $tf = new TrackFileCot($this->msg, $this->paths, $this->sender->fSys);
        // En el metodo ->finOfCotizacion se hace:
        // 1.- Recontruimos el objeto para iniclizar variables y buscar el bait en progreso
        // 2.- Eliminar el archivo que indica cotizando
        // 3.- Eliminar del estanque el bait que se cotizó y enviarlo a tracked
        // 4.- Buscar una nueva carnada
        $tf->finOfCotizacion();
        if(count($tf->baitProgress) == 0) {
            return;
        }

        $this->cotProgress = $tf->baitProgress;
        //Buscamos para ver si existe el mensaje del item prefabricado.
        $this->sender->subEvento = 'next_bait';
        $this->sender->fSys->setPathBase($this->paths['prodTrack']);
        $template = $tf->fSys->getContent($this->cotProgress['idItem'].'_track.json');
        if(count($template) > 0) {
            if(array_key_exists('message', $template)) {
                $template = $template['message'];
                $this->sender->updateCotProgress($this->cotProgress);
                $this->sender->getTemplate($template);
                $this->sender->sent();
            }
        }
    }

}
