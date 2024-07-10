<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;
use App\Service\AnetTrack\HcFotos;
use App\Service\AnetTrack\HcDetalles;
use App\Service\AnetTrack\HcCosto;
use App\Service\AnetTrack\HcFinisherCot;

class HandlerQuote
{
    public WaMsgDto $waMsg;
    public Fsys $fSys;
    public WaSender $waSender;
    public String $fileTmp = '';

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
        $bait = $this->fSys->getContent('tracking', $this->waMsg->from.'.json');
        if(count($bait) == 0) {
            // TODO alertar que el item a cotizar no existe, o tratar de recuperarlo
            return;
        }else{
            if($this->waMsg->idItem == '' && $bait['idItem'] != '') {
                $this->waMsg->idItem = $bait['idItem'];
            }
            if($this->waMsg->context == '' && $bait['wamid'] != '') {
                $this->waMsg->context = $bait['wamid'];
            }
            file_put_contents('message_process.json', json_encode($this->waMsg->toArray()));
        }

        if($this->waMsg->subEvento == 'cnc') {
            //-> Cancelar cotizacion en curso
            $handler = new HcFinisherCot($this->waSender, $this->waMsg, $bait);
            $handler->exe('cancel');
            return;
        }elseif($this->waMsg->subEvento == 'ccc') {
            
        }

        switch ($bait['current']) {
            case 'sfto':
                $handler = new HcFotos($this->fSys, $this->waSender, $this->waMsg, $bait);
                $handler->exe();
                break;
            case 'sdta':
                if($this->waMsg->tipoMsg == TypesWaMsgs::IMAGE) {
                    $handler = new HcFotos($this->fSys, $this->waSender, $this->waMsg, $bait);
                    $handler->exe();
                }else {
                    $handler = new HcDetalles($this->fSys, $this->waSender, $this->waMsg, $bait);
                    $handler->exe();
                }
                break;
            case 'scto':
                $handler = new HcCosto($this->fSys, $this->waSender, $this->waMsg, $bait);
                $handler->exe();
                break;
            default:
                # code...
                break;
        }

    }

}
