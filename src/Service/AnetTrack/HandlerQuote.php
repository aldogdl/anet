<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;

class HandlerQuote
{
    public WaMsgDto $waMsg;
    public Fsys $fSys;
    public WaSender $waSender;
    public String $fileTmp = '';
    public array $bait = [];

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
    public function exe()
    {

        $this->bait = $this->fSys->getContent('tracking', $this->waMsg->from.'.json');
        $this->seg('el_bait');
        if(count($this->bait) == 0) {
            // TODO alertar que el item a cotizar no existe, o tratar de recuperarlo
            return;
        }
        
        $this->seg('el_bait_paso');
        switch ($this->bait['current']) {
            case 'nfto':
                $handler = new HcFotos($this);
                $handler->exe();
                break;
            case 'sfto':
                $handler = new HcFotos($this);
                $handler->exe();
                break;
            case 'sdta':
                # code...
                break;
            case 'scto':
                # code...
                break;
            default:
                # code...
                break;
        }
        $this->fSys->setContent('tracking', $this->waMsg->from.'.json', $this->bait);
    }

    ///
    public function seg(String $filename) {
        $this->fSys->setContent('/', 'wa_'.$filename.'json', []);
    }
}
