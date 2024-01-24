<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class InteractiveProcess
{

    private TrackFileCot $tf;
    private WebHook $wh;
    private WrapHttp $wapiHttp;
    private array $paths;
    private array $template = [];
    private array $returnBait = [];
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
        $this->tf = new TrackFileCot($message, $paths);
        $this->wh = $wh;
        $this->wapiHttp = $wapiHttp;
        $this->paths = $paths;

        $this->template = [];
        $this->hasTemplate = false;

        if($message->subEvento == 'ntg' || $message->subEvento == 'ntga') {
            $this->tratarConNtg($message);
        }

        if(mb_strpos($message->subEvento, '.') !== false) {
            $this->tratarConRespRapidas($cotProgress);
        }

        if(!$this->hasTemplate) {
            $this->tratarCotizarAhora();
        }

        if($this->hasTemplate) {
            $this->sentTemplate();
        }
    }

    /** */
    private function tratarConNtg(): void
    {
        // Buscamo una carnada en el estanque
        $newBait = $this->tf->lookForBait();
        
        if(count($newBait) > 0) {

            // Se encontro una carnada para enviar por lo tanto, buscamos para ver si existe
            // el mensaje prefabricado del item encontrado.
            $this->tf->fSys->setPathBase($this->paths['prodTrack']);
            $template = $this->tf->fSys->getContent($newBait['idItem'].'_track.json');
            if(count($template) > 0) {
                if(array_key_exists('message', $template)) {
                    $this->template = $template['message'];
                    $this->hasTemplate = true;
                    $this->returnBait = $this->tf->getEstanqueReturn($newBait, 'bait');
                    // Si hidratamo la plantilla a ser enviada al cotizador nos regresamos
                    return;
                }
            }
        }

        if(!$this->hasTemplate) {

            $this->returnBait = [];
            // No se encotró una carnada para enviar, por lo tanto, enviar mensaje de gracias enterados
            $this->tf->fSys->setPathBase($this->paths['waTemplates']);
            // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
            $template = $this->tf->fSys->getContent($this->tf->message->subEvento.'.json');
            if(count($template) > 0) {
                $this->template = $template;
                $this->hasTemplate = true;
                $this->returnBait = $this->tf->getEstanqueReturn($this->tf->cotProcess, 'less');
            }
        }
    }

    /** */
    private function tratarConRespRapidas(array $cotProgress): void
    {

        $saveCotProcess = false;
        $respRapida = '';
        $partes = explode('.', $this->tf->message->subEvento);
        $this->tf->message->subEvento = $partes[0];
        $respRapida = $partes[1];
        
        $saveCotProcess = false;
        if($this->tf->message->subEvento == 'sdta' && $cotProgress['current'] == 'sfto') {
            // Estamos en fotos y preciono un boton de opcion
            if($respRapida == 'fton') {
                $cotProgress['current'] = 'sdta';
                $cotProgress['next'] = 'scto';
                $cotProgress['track']['fotos'] = [];
                $saveCotProcess = true;
            }
        }
        
        if($this->tf->message->subEvento == 'scto' && $cotProgress['current'] == 'sdta') {
            // Estamos en detalles y preciono un boton de opcion
            if($respRapida == 'uso') {
                $cotProgress['current'] = 'scto';
                $cotProgress['next']    = 'sgrx';
                $cotProgress['track']['detalles'] = 'La pieza cuenta con Detalles de Uso';
                $saveCotProcess = true;
            }
        }

        $cotProgress = $this->getTemplate($cotProgress);

        if($saveCotProcess) {
            $this->tf->fSys->setPathBase($this->paths['cotProgres']);
            $this->tf->fSys->setContent($this->tf->message->from.'.json', $cotProgress);
        }

        if($this->hasTemplate) {
            $this->returnBait = $this->tf->getEstanqueReturn($cotProgress, 'less');
            return;
        }
    }

    /** */
    private function tratarCotizarAhora()
    {
        $saveCotProcess = false;
        $this->tf->build();
        if(count($this->tf->cotProcess) == 0) {
            // TODO La solicitud ya no esta disponible MSG al cliente
            return;
        }
        
        if(array_key_exists('track', $this->tf->cotProcess)) {
            if(!array_key_exists('idCot', $this->tf->cotProcess['track'])) {
                $createCotProgress = true;
            }
        }else{
            $createCotProgress = true;
        }

        $this->tf->cotProcess['sended'] = round(microtime(true) * 1000);
        if($createCotProgress && $this->tf->message->subEvento == 'sfto') {
            // Si no hay ningun archivo que indica cotizacion en progreso lo creamos
            $this->tf->cotProcess['track'] = ['idCot' => time()];
            $saveCotProcess = true;
        }
        
        $this->tf->cotProcess = $this->getTemplate($this->tf->cotProcess);
        // Si el mensaje es el inicio de una cotizacion creamos un archivo especial
        if($saveCotProcess) {
            $this->tf->fSys->setPathBase($this->paths['cotProgres']);
            $this->tf->fSys->setContent($this->tf->message->from.'.json', $this->tf->cotProcess);
        }

        $this->returnBait = $this->tf->getEstanqueReturn($this->tf->cotProcess, 'less');
    }

    /** */
    private function getTemplate(array $cotProgress): array
    {
        $this->tf->fSys->setPathBase($this->paths['waTemplates']);
        // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
        $template = $this->tf->fSys->getContent($this->tf->message->subEvento.'.json');
        
        if(count($template) > 0) {
            $this->hasTemplate = true;
        }else{
            $this->hasTemplate = false;
            return [];
        }

        // Buscamos si contiene AnetLanguage para decodificar
        $deco = new DecodeTemplate($cotProgress);
        $template = $deco->decode($template);

        $contexto = '';
        if(array_key_exists('wamid_cot', $cotProgress)) {
            $contexto = $cotProgress['wamid_cot'];
        }else{
            if(strlen($this->tf->message->context) > 0) {
                $contexto = $this->tf->message->context;
            }
        }

        if(strlen($contexto) > 0) {
            $template['context']      = $contexto;
            $cotProgress['wamid_cot'] = $contexto;
        }

        if($this->hasTemplate) {
            $this->template = $template;
        }
        return $cotProgress;
    }

    /** */
    private function sentTemplate()
    {
        $sended = [];
        $typeMsgToSent = 'text';
        $conm = new ConmutadorWa($this->tf->message->from, $this->paths['tkwaconm']);

        $typeMsgToSent = $this->template['type'];
        $conm->setBody($typeMsgToSent, $this->template);

        $result = $this->wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $this->wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }

        $objMdl = $conm->setIdToMsgSended($this->tf->message, $result);
        $this->returnBait['bait']['wamid'] = $objMdl->id;

        $conm->bodyRaw = $this->template[$typeMsgToSent]['body'];
        $sended = $objMdl->toArray();
        $msg    = $this->tf->message->toArray();

        $this->tf->fSys->setPathBase($this->paths['chat']);
        $this->tf->fSys->dumpIn($msg);
        $this->tf->fSys->dumpIn($sended);

        $this->wh->sendMy(
            'wa-wh', 'notSave', [
                'recibido' => $msg, 'enviado'  => $sended, 'estanque' => $this->returnBait
            ]
        );
    }

}
