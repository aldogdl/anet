<?php

namespace App\Service\AnetTrack;

use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;

class HcDetalles
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    private String $txtValid = '';

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
        $this->prepareStep();
        if($this->waMsg->tipoMsg == TypesWaMsgs::INTERACTIVE) {
            if(mb_strpos($this->waMsg->subEvento, 'uso') !== false) {
                $this->waMsg->content = 'Detalles normales de uso.';
            }
        }else{
            // Validamos la integridad del tipo de mensaje
            if(!$this->isValid() && $this->txtValid != '') {
                $this->waSender->sendText($this->txtValid);
                return;
            }
        }
        $this->editarBait();
        $this->enviarMsg();
        return;
    }

    /** */
    private function createFilenameTmpOf(String $name): String
    {
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
        // Creamos el archivo indicativo del proceso actual con una nueva marca de tiempo
        $filename = $this->createFilenameTmpOf('sdta');
        $this->fSys->setContent('/', $filename, ['']);

        // Eliminamos el archivo indicativo del proceso anterior
        $filename = $this->fSys->startWith($this->waMsg->from.'_sfto_');
        if($filename != '') {
            $this->fSys->delete('/', $filename);
        }
    }

    /** */
    private function editarBait(): void
    {
        $this->bait['track']['detalles'] = $this->waMsg->content;
        $this->bait['current'] = 'scto';
        $this->waMsg->subEvento = 'sdta';
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->bait);
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = '';
        
        if($this->waMsg->tipoMsg != TypesWaMsgs::TEXT) {
            $this->txtValid = "⚠️ ¡Lo sentimos!, El sistema está preparado ".
            'para aceptar *sólo texto* en los detalles.';
            return false;
        }

        $notFto = false;
        if(!array_key_exists('track', $this->bait)) {
            $notFto = true;
        }else{
            $track = $this->bait['track'];
            if(array_key_exists('fotos', $track)) {
                $notFto = (count($track['fotos']) > 0) ? false : true;
            }else{
                $notFto = true;
            }
        }
        if($notFto) {
            $this->txtValid = "⚠️ ¡Lo sentimos!, pero es muy importante que ".
            "por lo menos envíes una Imagen de tu pieza.";
            return false;
        }

        // Si eliminamos todos los numeros de la descripcion y no quedan letras
        // es que la descripcion no esta bien
        $value = preg_replace('/[0-9]/', '', $this->waMsg->content);
        if(strlen($value) < 3) {
            $this->txtValid = "⚠️ Se un poco más específico con la descripción\n".
            "Es necesario *letras y números*, o *sólo letras*.";
            return false;
        }

        return true;
    }

    /** */
    private function enviarMsg(): void
    {
        $this->waSender->context = $this->bait['wamid'];
        
        $builder = new BuilderTemplates($this->fSys, $this->waMsg);
        $template = $builder->exe('scto');

        $res = 500;
        if(count($template) > 0) {
            $res = $this->waSender->sendPreTemplate($template);
        }else{
            $res = $this->waSender->sendText(
                "😃👍 Perfecto!!!\n*¿Cuál sería tu mejor PRECIO?.*\n\n".
                "_💰 Escribe solo números por favor._"
            );
        }
        if($res >= 200 && $res <= 300) {

            $headers = $this->waMsg->toStt(true);
            $encoding = mb_detect_encoding($this->waMsg->content, ['UTF-8', 'ISO-8859-1', 'ASCII']);
            if ($encoding !== 'UTF-8') {
                $valorCabecera = mb_convert_encoding($this->waMsg->content, 'UTF-8', $encoding);
            } else {
                $valorCabecera = $this->waMsg->content;
            }
            $headers = HeaderDto::setValue($headers, $valorCabecera);
            $this->waSender->sendMy(['header' => $headers]);
        }
    }

}
