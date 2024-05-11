<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;

class HcCosto
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
        // Validamos la integridad del tipo de mensaje
        if(!$this->isValid() && $this->txtValid != '') {
            $this->waSender->sendText($this->txtValid);
            return;
        }
        $this->prepareStep();
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
        // Eliminamos el archivo indicativo del proceso anterior
        $filename = $this->createFilenameTmpOf('sdta');
        if($this->isAtendido($filename)) {
            $this->fSys->delete('/', $filename);
        }
        $filename = $this->createFilenameTmpOf('scto');
        $this->fSys->setContent('/', $filename, ['']);
    }

    /** */
    private function editarBait(): void
    {
        $this->bait['track']['costo'] = $this->waMsg->content;
        $this->bait['current'] = 'sgrx';
        $this->waMsg->subEvento = 'scto';
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->bait);
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = "⚠️ ¡Lo sentimos!, El sistema está preparado ".
        'para aceptar *sólo números* para el COSTO.';

        if($this->waMsg->tipoMsg != TypesWaMsgs::TEXT) {
            return false;
        }

        $notDta = false;
        if(!array_key_exists('track', $this->bait)) {
            $notDta = true;
        }else{
            $track = $this->bait['track'];
            if(array_key_exists('detalles', $track)) {
                $notDta = (strlen($track['detalles']) > 0) ? false : true;
            }else{
                $notDta = true;
            }
        }
        if($notDta) {
            $this->txtValid = "⚠️ ¡Lo sentimos!, pero es muy importante que ".
            "indiques los detalles de la pieza.";
            return false;
        }

        $result = $this->waMsg->content;
        $str = mb_strtolower($result);
        if(mb_strpos($str, 'mil') !== false) {
            return false;
        }

        $str = str_replace('$', '', $str);
        $str = str_replace(',', '', $str);

        if(mb_strpos($str, '.') !== false) {

            $partes = explode('.', $str);
            $entera = $this->isDigit($partes[0]);
            if($entera != '-1') {
                $decimal = $this->isDigit($partes[1]);
                if($decimal != '-1') {
                    $this->txtValid = '';
                    $this->waMsg->content = implode('.', $partes);
                    return true;
                }
            }
        }

        $entera = $this->isDigit($str);
        if($entera != '-1') {
            $this->txtValid = '';
            $this->waMsg->content = $str;
            return true;
        }

        return false;
    }

    /**
     * Checamos si el valor dado es un numero.
     */
    private function isDigit(String $value) : String
    {
        $value = preg_replace('/[^0-9]/', '', $value);
        if(strlen($value) > 2) {
            if(is_int($value) || ctype_digit($value)) {
                return $value;
            }
        }
        return '-1';
    }

    /** */
    private function enviarMsg(): void
    {
        $res = $this->waSender->sendText(
            "🤩 *Finalizaste con ÉXITO!.*\n\n".
            "*RECUERDA*: Puedes vaciar este chat para mantener limpio tu dispositivo\n\n".
            "_NUNCA PERDERÁS ningúna OPORTUNIDAD DE VENTA_💰"
        );
        if($res >= 200 && $res <= 300) {
            $this->waSender->sendMy($this->waMsg->toMini());
        }
    }

}
