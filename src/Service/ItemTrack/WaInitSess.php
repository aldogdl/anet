<?php

namespace App\Service\ItemTrack;

use App\Service\Pushes;
use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Repository\FcmRepository;

class WaInitSess
{
    public String $hasErr = '';
    private String $fileTmp = '';

    private WaMsgDto $waMsg;
    private WaSender $waSender;
    private Pushes $push;
    private FcmRepository $fcmEm;
    
    /** */
    public function __construct(
        ?FcmRepository $fbm, ?Pushes $push, WaSender $waS, WaMsgDto $msg
    )
    {
        $this->waMsg   = $msg;
        $this->waSender= $waS;
        if($fbm != null) {
            $this->fcmEm = $fbm;
        }
        if($push != null) {
            $this->push = $push;
        }
        $this->fileTmp = $this->waMsg->from.'_'.$this->waMsg->subEvento.'.json';
    }

    /** 
     * Cuando Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto, reenvia el mensaje
     * a este mismo servidor causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos el problema descrito.
    */
    public function isAtendido(): bool {

        $fecha = $this->waSender->fSys->getContent('/', $this->fileTmp);
        if(array_key_exists('init', $fecha)) {

            $fechaDateTime = \DateTime::createFromFormat('Y-m-d\TH:i:s.v', $fecha['init']);
            $fechaDateTime->sub(new \DateInterval('PT5M'));
    
            $fechaActual = new \DateTime();
            $diff = $fechaActual->diff($fechaDateTime);
            return ($diff->h >= 24) ? false : true;
        }
        return false;
    }

    /** 
     * [V6]
    */
    public function exe() {

        if($this->isAtendido()) {
            return;
        }

        try {
            $date = new \DateTime(strtotime($this->waMsg->creado));
        } catch (\Throwable $th) {
            $date = new \DateTime('now');
        }
        $fechHra = $date->format('Y-m-d\TH:i:s.v');
        $this->waSender->fSys->setContent('/', $this->fileTmp, ['init' => $fechHra]);
        
        // Guardamos la marca de login en la BD de FB
        $slugFrom = $this->fcmEm->setLoggedFromWhats($this->waMsg->from, $fechHra);
        if(count($slugFrom) > 0) {
            // Guardamos la marca de login en el archivo del expediente del usuario
            $this->waSender->fSys->updateFechaLoginTo($slugFrom[0]['slug'], $this->waMsg->from, $fechHra);
            // Enviamos una notificacion push para que reaccione la app cliente
            $this->push->sendPushInitLogin($slugFrom, $fechHra);
        }
        
        $this->hasErr = '';
        $this->waSender->setConmutador($this->waMsg);
        $code = $this->waSender->sendText(
            "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana a las " . $date->format('h:i:s a')
        );

        if($code >= 200 && $code <= 300) {
            $headers = $this->waMsg->toInit();
            $this->waSender->sendMy(['header' => $headers]);
        }
    }

}
