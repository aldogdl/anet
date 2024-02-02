<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;
use App\Service\WapiProcess\SentTemplate;

class InteractiveProcess
{

    private TrackFileCot $tf;
    private WebHook $wh;
    private WrapHttp $wapiHttp;
    private WaMsgMdl $msg;
    private array $paths;
    private SentTemplate $sender;
    private array $cotProgress = [];
    private bool $hasTemplate = false;

    /** 
     * Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
     * que se necesita enviar como respuesta inmendiata a este.
     * Este dato debe de colacarce en la propiedad subEvento del Objeto WaMsgMdl creado
     * en la clase: @see App\Service\WapiRequest\ExtractMessage()
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){
        $this->wh          = $wh;
        $this->wapiHttp    = $wapiHttp;
        $this->paths       = $paths;
        $this->msg         = $message;
        $this->cotProgress = $cotProgress;
        $this->hasTemplate = false;
        $cotProgress       = [];
    }

    /** */
    public function exe() : void
    {
        if(count($this->cotProgress) == 0 || !array_key_exists('idItem', $this->cotProgress)) {
            return;
        }

        $this->tf = new TrackFileCot($this->msg, $this->paths);
        $this->sender = new SentTemplate(
            $this->msg, $this->wh, $this->wapiHttp, $this->paths, $this->cotProgress
        );

        if($this->msg->subEvento == 'ntg' || $this->msg->subEvento == 'ntga') {
            $this->tratarConNtg($this->msg);
        }

        if(mb_strpos($this->msg->subEvento, '.') !== false) {
            $this->tratarConRespRapidas();
        }

        if( array_key_exists('title', $this->msg->message) ) {
            if($this->msg->message['title'] == 'COTIZAR AHORA')
            $this->tratarCotizarAhora();
        }

        if($this->sender->hasTemplate) {
            $this->sender->sent();
            $this->sender->saveCotProgress();
        }
    }

    /** */
    private function tratarConNtg(): void
    {
        $this->sender->hasTemplate = false;
        // Buscamo una carnada en el estanque a su ves, eliminamos del estanque el bait que se
        // esta atendiendo actualmente.
        $newBait = $this->tf->lookForBait();
        if(count($newBait) > 0) {

            // Se encontro una carnada para enviar por lo tanto, buscamos para ver si existe
            // el mensaje prefabricado del item encontrado.
            $this->tf->fSys->setPathBase($this->paths['prodTrack']);
            $template = $this->tf->fSys->getContent($newBait['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $this->sender->getTemplate($template['message']);
                    // Si hidratamo la plantilla a ser enviada al cotizador no hacemos mas...
                    return;
                }
            }
        }

        // No se encotró una carnada para enviar, por lo tanto, enviar mensaje de gracias enterados
        if(!$this->hasTemplate) {
            $this->sender->getTemplate();
        }
    }

    /** */
    private function tratarCotizarAhora()
    {
        // Este se usa para cuando se vuelve a tomar un bait que coincida con el que se esta cotizando
        $isTackedOther = false;
        $hasCriticalErro = false;
        if($this->cotProgress['idItem'] != $this->msg->message['idItem']) {
            $this->tf->fetchBaitProgress();
            $isTackedOther = true;
            if(count($this->tf->baitProgress) == 0) {
                $hasCriticalErro = true;
            }
            if($this->tf->baitProgress['idItem'] != $this->msg->message['idItem']) {
                $hasCriticalErro = true;
            }
        }

        if($hasCriticalErro) {
            // TODO La solicitud ya no esta disponible MSG al cliente
            return;
        }
        if($isTackedOther) {
            $this->cotProgress = $this->tf->baitProgress;
        }

        if(!array_key_exists('track', $this->cotProgress)) {
            $this->cotProgress['track'] = ['idCot' => time()];
        }else{
            if(!array_key_exists('idCot', $this->cotProgress['track'])) {
                $this->cotProgress['track'] = ['idCot' => time()];
            }
        }
        $this->cotProgress['sended'] = round(microtime(true) * 1000);

        $this->sender->updateCotProgress($this->cotProgress);
        $this->sender->getTemplate();
    }
    
    /** */
    private function tratarConRespRapidas(): void
    {
        $respRapida = '';
        $partes = explode('.', $this->tf->message->subEvento);
        $this->msg->subEvento = $partes[0];
        $respRapida = $partes[1];
        
        if($this->msg->subEvento == 'sdta' && $this->cotProgress['current'] == 'sfto') {
            // Estamos en fotos y preciono un boton de opcion
            if($respRapida == 'fton') {
                $this->cotProgress['current'] = 'sdta';
                $this->cotProgress['next'] = 'scto';
                $this->cotProgress['track']['fotos'] = [];
            }
        }

        if($this->tf->message->subEvento == 'scto' && $this->cotProgress['current'] == 'sdta') {
            // Estamos en detalles y preciono un boton de opcion
            if($respRapida == 'uso') {
                $this->cotProgress['current'] = 'scto';
                $this->cotProgress['next']    = 'sgrx';
                $this->cotProgress['track']['detalles'] = 'La pieza cuenta con Detalles de Uso';
            }
        }

        $this->sender->updateCotProgress($this->cotProgress);
        $this->sender->getTemplate();
    }

}
