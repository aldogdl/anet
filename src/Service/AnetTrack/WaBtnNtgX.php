<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;

class WaBtnNtgX
{
    private WaMsgDto $waMsg;
    private Fsys $fSys;
    private WaSender $waSender;
    private String $fileTmp = '';

    /** */
    public function __construct(Fsys $fsys, WaSender $waS, WaMsgDto $msg)
    {
        $this->waMsg     = $msg;
        $this->fSys      = $fsys;
        $this->waSender  = $waS;
        $this->fileTmp   = $this->waMsg->from.'_'.$this->waMsg->subEvento.'.json';
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(): bool { return $this->fSys->existe('/', $this->fileTmp); }

    /** */
    public function exe(bool $hasCotInProgress)
    {
        if($this->isAtendido()) {
            return;
        }
        $this->fSys->setContent('/', $this->fileTmp, ['']);

        $this->fSys->putCotizando($this->waMsg);
        if($hasCotInProgress) {
            // TODO abisar que hay una cotizacion en progreso y dar opcion a cancelar o seguir
            // con la que esta en progreso.
            // $this->waSender->setConmutador($this->waMsg);
            return;
        }
        
        $this->waSender->setConmutador($this->waMsg);
        $code = $this->waSender->sendTemplate($this->waMsg->idItem);
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($this->waMsg->toMini());
        }
    }

}
