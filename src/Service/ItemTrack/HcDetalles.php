<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\MyFsys;
use App\Service\ItemTrack\WaSender;

class HcDetalles
{
    private MyFsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $item;
    private String $txtValid = '';

    /** */
    public function __construct(MyFsys $fsys, WaSender $waS, WaMsgDto $msg, array $theItem)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->item = $theItem;
        if($this->waMsg->idDbSr == '') {
            $this->waMsg->idDbSr = $this->item['idDbSr'];
        }
        $this->waSender->setConmutador($this->waMsg);
    }

    /** 
     * [V6]
    */
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
        $this->item['resp']['detalles'] = $this->waMsg->content;
        $this->item['current'] = 'scto';
        $this->waMsg->subEvento = 'sdta';
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->item);
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
        if(!array_key_exists('resp', $this->item)) {
            $notFto = true;
        }else{
            $resp = $this->item['resp'];
            if(array_key_exists('fotos', $resp)) {
                $notFto = (count($resp['fotos']) > 0) ? false : true;
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
        $this->waSender->context = $this->item['wamid'];
        
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
            if(mb_strpos($this->waMsg->idDbSr, 'demo') === false) {
                $this->waSender->sendMy(['header' => $this->waMsg->toStt(true)]);
            }
        }
    }

}
