<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\MyFsys;
use App\Service\ItemTrack\WaSender;
use App\Service\ItemTrack\HcFotos;
use App\Service\ItemTrack\HcDetalles;
use App\Service\ItemTrack\HcCosto;
use App\Service\ItemTrack\HcFinisherCot;

class HandlerQuote
{
    public WaMsgDto $waMsg;
    public MyFsys $fSys;
    public WaSender $waSender;
    public String $fileTmp = '';

    /** */
    public function __construct(MyFsys $fsys, WaSender $waS, WaMsgDto $msg)
    {
        $this->waMsg     = $msg;
        $this->fSys      = $fsys;
        $this->waSender  = $waS;
        $this->fileTmp   = $this->waMsg->from.'_'.$this->waMsg->subEvento.'.json';
    }

    /** 
     * Cuando Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(): bool { return $this->fSys->existe('/', $this->fileTmp); }

    /** 
     * [V6]
    */
    public function exe()
    {
        $item = $this->fSys->getContent('tracking', $this->waMsg->from.'.json');
        if(count($item) == 0) {
            // TODO alertar que el item a cotizar no existe, o tratar de recuperarlo
            return;
        }else{
            if($this->waMsg->idDbSr == '' && $item['idDbSr'] != '') {
                $this->waMsg->idDbSr = $item['idDbSr'];
            }
            if($this->waMsg->context == '' && $item['wamid'] != '') {
                $this->waMsg->context = $item['wamid'];
            }
        }

        if($this->waMsg->subEvento == 'cnc') {
            //-> Cancelar cotizacion en curso
            $handler = new HcFinisherCot($this->waSender, $this->waMsg, $item);
            $handler->exe('cancel');
            return;
        }elseif($this->waMsg->subEvento == 'ccc') {

        }

        switch ($item['current']) {
            case 'sfto':
                $handler = new HcFotos($this->fSys, $this->waSender, $this->waMsg, $item);
                $handler->exe();
                break;
            case 'sdta':
                if($this->waMsg->tipoMsg == TypesWaMsgs::IMAGE) {
                    $handler = new HcFotos($this->fSys, $this->waSender, $this->waMsg, $item);
                    $handler->exe();
                }else {
                    $handler = new HcDetalles($this->fSys, $this->waSender, $this->waMsg, $item);
                    $handler->exe();
                }
                break;
            case 'scto':
                $handler = new HcCosto($this->fSys, $this->waSender, $this->waMsg, $item);
                $handler->exe();
                break;
            default:
                # code...
                break;
        }

    }

}
