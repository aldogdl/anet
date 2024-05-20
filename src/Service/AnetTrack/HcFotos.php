<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;

class HcFotos
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    private String $txtValid = '';
    private bool $sendMsgDeta = true;

    /** */
    public function __construct(Fsys $fsys, WaSender $waS, WaMsgDto $msg, array $bait)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->bait = $bait;
        $this->waSender->setConmutador($this->waMsg);
    }

    /** */
    public function exe(): void
    {
        $processValid = true;
        if($this->waMsg->tipoMsg == TypesWaMsgs::INTERACTIVE) {
            
            if(mb_strpos($this->waMsg->subEvento, 'nfto') !== false) {
                $this->sendMsgDeta = true;
                $this->enviarMsg('nfto');
                return;
            }elseif(mb_strpos($this->waMsg->subEvento, 'fton') !== false) {
                // El usuario desea continuar sin fotos
                $processValid = false;
                $this->waMsg->content = ['id' => 0, 'mime_type' => 'none'];
                $this->bait['current'] = 'sdta';
            }else {
                // El usuario se arrepintio desea continuar con fotos
                $this->enviarMsg('sfto');
                return;
            }
        }

        // Solo en las imagenes es necesario primero preparar el escenario
        // antes de validar los datos recibidos por la cuestion del envio de fotos.
        if($processValid) {
            $this->prepareStep();
            $this->bait['current'] = 'sdta';
            // Validamos la integridad del tipo de mensaje
            if(!$this->isValid() && $this->txtValid != '') {
                $this->waSender->sendText($this->txtValid);
                return;
            }
        }

        $oldCurrent = $this->bait['current'];
        $this->editarBait();
        // Si $processValid es false quiere decir que se presiono el btn de
        // continuar sin fotos.
        $this->enviarMsg($oldCurrent, $processValid);
        return;
    }

    /** */
    private function createFilenameTmpOf(String $name, bool $withTime = false): String
    {
        if($withTime) {
            $tiempo_actual = (integer) microtime(true) * 1000;
            return $this->waMsg->from.'_'.$name.'_'.$tiempo_actual.'_.json';
        }
        return $this->waMsg->from.'_'.$name.'.json';
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(String $filename): bool
    {
        return $this->fSys->existe('/', $filename);
    }

    /** 
     * Tratamos con los archivos indicativos del paso en el que actualmente se
     * encuentra la cotizacion
    */
    private function prepareStep(): void
    {
        $filename = $this->fSys->startWith($this->waMsg->from.'_sfto_');
        if($filename != '') {
            $this->fSys->delete('/', $filename);
            // Si ya existe el archivo lo partimos en sus partes para optener el momento
            // que este archivo se creo
            $partes = explode('_', $filename);
            $rota = count($partes) -1;
            $lastTime = (integer) $partes[$rota - 1];
            $tiempo_actual = (integer) microtime(true) * 1000;
            $diff = ($tiempo_actual - $lastTime)/1000;
            $this->sendMsgDeta = ($diff > 3) ? true : false;
        }
        
        // Creamos el archivo indicativo del proceso actual con una nueva marca de tiempo
        $filename = $this->createFilenameTmpOf('sfto', true);
        $this->fSys->setContent('/', $filename, ['']);

        // Eliminamos el archivo indicativo del proceso anterior
        $filename = $this->createFilenameTmpOf('cnow');
        if($this->isAtendido($filename)) {
            $this->fSys->delete('/', $filename);
        }
    }

    /** */
    private function editarBait(): void
    {
        $track = [];
        if(array_key_exists('track', $this->bait)) {
            $track = $this->bait['track'];
        }
        if(!array_key_exists('fotos', $track)) {
            $track['fotos'] = [$this->waMsg->content];
        }else{
            if(count($track['fotos']) > 0) {
                $idsFtos = array_column($track['fotos'], 'id');
                $has = array_search($this->waMsg->content['id'], $idsFtos);
                if($has !== false) {
                    return;
                }
                array_push($track['fotos'], $this->waMsg->content);
            }
        }

        $this->bait['track'] = $track;
        $this->bait['current'] = 'sdta';
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->bait);
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = '';

        if($this->waMsg->tipoMsg != TypesWaMsgs::IMAGE) {
            $this->txtValid = "⚠️ ¡Lo sentimos!, El sistema está preparado ".
            'para aceptar *sólo imágenes* para las fotos de la pieza.';
            return false;
        }
        
        $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
        if(!in_array($this->waMsg->status, $permitidas)) {
            $this->txtValid = "⚠️ Lo sentimos pero el formato de imagen (*".
            $this->waMsg->status."*) no está entre la lista de imágenes ".
            "permididas, por el momento sólo aceptamos fotos con extención:\n".
            "[*".implode(', ', $permitidas)."]*.";
            return false;
        }
        if(count($this->waMsg->content) == 0) {
            $this->txtValid = "⚠️ Lo sentimos pero la imagen ".
            "recibida *no es valida*, por favor intenta enviarla nuevamente ".
            "o evnía otra como segunda opción.";
            return false;
        }

        if(!array_key_exists('id', $this->waMsg->content)) {
            $this->txtValid = "⚠️ Lo sentimos pero la imagen ".
            "*no se envio correctamente* a WHATSAPP, intenta enviarla ".
            "nuevamente por favor.";
            return false;
        }

        return true;
    }

    /** */
    private function enviarMsg(String $oldCurrent, bool $resent = true): void
    {
        if(!$this->sendMsgDeta) {
            return;
        }

        $builder = new BuilderTemplates($this->fSys, $this->waMsg);

        // Para esta plantilla de solicitud de detalles enviamos una
        // serie de mensajes al azar para interactual con el usuario
        $template = $builder->exe($oldCurrent, $this->waMsg->idItem);
        if($oldCurrent == 'sdta' && $resent) {
            $template = $builder->editForDetalles($template);
        }

        $this->waSender->context = $this->bait['wamid'];
        if(count($template) > 0) {
            $res = $this->waSender->sendPreTemplate($template);
            if($oldCurrent == 'sdta') {
                if($res >= 200 && $res <= 300) {
                    $this->waSender->sendMy($this->waMsg->toMini());
                }
            }
        }else{

            if($oldCurrent == 'sdta') {
                $this->waSender->sendText(
                    "*Muy bien gracias*.\n\n📝Ahora puedes describir un poco la ".
                    "CONDICIÓN O ESTADO de tu autoparte por favor."
                );
            }else{
                $this->waSender->sendText(
                    "⚠️ *Lo sentimos mucho*.\n\nTu solicitud no fué aceptada ".
                    "por favor, envia por lo menos 1 fotografía."
                );
            }
        }
    }

}
