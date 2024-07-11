<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;

class WaInitSess
{

    public String $hasErr = '';

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
    public function exe() {

        $this->waSender->setConmutador($this->waMsg);
        if($this->isAtendido()) {
            $code = $this->waSender->sendText("🎟️ Gracias, ya tienes una sesión en curso Activa");
            return;
        }
        $this->fSys->setContent('/', $this->fileTmp, ['']);

        $cuando = '';
        try {
            $date = new \DateTime(strtotime($this->waMsg->creado));
            $timeFin = $date->format('h:i:s a');
        } catch (\Throwable $th) {
            $this->hasErr = $th->getMessage();
        }
        
        if($this->hasErr == '') {$cuando = " a las " . $timeFin;}
        $code = $this->waSender->sendText(
            "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando
        );
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($this->waMsg->toInit());
        }
    }
}
