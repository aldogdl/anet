<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;

class HcFotos
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    private String $txtValid = '';
    private int $lastTime = 0;

    /** */
    public function __construct(Fsys $fsys, WaSender $waS, WaMsgDto $msg, array $bait)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->bait = $bait;
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

    /** */
    public function exe(): array
    {
        $this->prepareStep();
        // Validamos la integridad del tipo de mensaje
        if(!$this->isValid() && $this->txtValid != '') {
            $this->waSender->sendText($this->txtValid);
            return [];
        }
        $oldCurrent = $this->bait['current'];
        $this->editarBait();
        $this->enviarMsg($oldCurrent);
        return $this->bait;
    }

    /** 
     * Tratamos con los archivos indicativos del paso en el que actualmente se
     * encuentra la cotizacion
    */
    private function prepareStep()
    {
        $crear = true;
        $filename = $this->fSys->startWith($this->waMsg->from.'_sfto_');
        file_put_contents('wa_file_finder_'.$filename, '');
        if($filename != '') {
            if($this->isAtendido($filename)) {
                $crear = false;
            }
        }
        
        // Creamos el archivo indicativo del proceso actual en caso de no existir
        if($crear) {
            $filename = $this->createFilenameTmpOf('sfto', true);
            $this->fSys->setContent('/', $filename, ['']);
        }else{
            // Si ya existe el archivo lo partimos en sus partes para optener el momento
            // que este archivo se creo
            $partes = explode('_', $filename);
            $rota = count($partes) -1;
            $this->lastTime = (integer) $partes[$rota - 1];
            $tiempo_actual = (integer) microtime(true) * 1000;
            file_put_contents('wa_'.$this->lastTime.'.json', '');
            $diff = ($tiempo_actual - $this->lastTime)/1000;
            file_put_contents('wa_pas_'.$diff.'_seg.json', '');
        }

        // Eliminamos el archivo indicativo del proceso anterior
        $filename = $this->createFilenameTmpOf('cnow');
        if($this->isAtendido($filename)) {
            $this->fSys->delete('/', $filename);
        }
        $this->waSender->setConmutador($this->waMsg);
    }

    /** */
    private function editarBait()
    {
        $track = [];
        if(array_key_exists('track', $this->bait)) {
            $track = $this->bait['track'];
        }
        if(!array_key_exists('fotos', $track)) {
            $track['fotos'] = [$this->waMsg->content];
        }else{
            $idsFtos = array_column($track['fotos'], 'id');
            $has = array_search($this->waMsg->content['id'], $idsFtos);
            if($has !== false) {
                return [];
            }
            array_push($track['fotos'], $this->waMsg->content);
        }

        $this->bait['track'] = $track;
        $this->bait['current'] = 'sdta';
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->bait);
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = '';
        $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
        if(!in_array($this->waMsg->status, $permitidas)) {
            $this->txtValid = "Lo sentimos pero el formato de imagen (".
            $this->waMsg->status.") no está entre la lista de imágenes ".
            "permididas, por el momento sólo aceptamos fotos con extención:\n".
            "[".implode(', ', $permitidas)."].";
            return false;
        }
        if(count($this->waMsg->content) == 0) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "recibida no es valida, por favor intenta enviarla nuevamente ".
            "o evnía otra como segunda opción.";
            return false;
        }

        if(!array_key_exists('id', $this->waMsg->content)) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "no se envio correctamente a WHATSAPP, intenta enviarla ".
            "nuevamente por favor.";
            return false;
        }

        return true;
    }

    /** */
    private function enviarMsg(String $oldCurrent)
    {
        $builder = new BuilderTemplates($this->fSys, $this->waMsg);
        $template = $builder->exe('sdta');
        // Para esta plantilla de solicitud de detalles enviamos una
        // serie de mensajes al azar para interactual con el usuario
        if($oldCurrent == 'sdta') {
            $template = $builder->editForDetalles($template);
        }

        if(count($template) > 0) {
            $res = $this->waSender->sendInteractive($template);
            if($res >= 200 && $res <= 300) {
                $this->waSender->sendMy($this->waMsg->toMini());
            }
        }else{
            $this->waSender->sendText(
                "*Muy bien gracias*.\n\n📝Ahora puedes describir un poco la ".
                "CONDICIÓN O ESTADO de tu autoparte por favor."
            );
        }
    }

}
