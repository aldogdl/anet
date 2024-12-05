<?php

namespace App\Service\ItemTrack;

use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\Fsys;

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
     * Cuando Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto, reenvia el mensaje
     * a este mismo servidor causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos el problema descrito.
    */
    public function isAtendido(): bool { return $this->fSys->existe('/', $this->fileTmp); }

    /** 
     * [V6]
    */
    public function exe() {

        if($this->isAtendido()) {
            return;
        }
        $this->fSys->setContent('/', $this->fileTmp, ['']);
        
        $this->hasErr = '';
        $this->waSender->setConmutador($this->waMsg);

        try {
            $date = new \DateTime(strtotime($this->waMsg->creado));
        } catch (\Throwable $th) {
            $date = new \DateTime('now');
        }
        $code = $this->waSender->sendText(
            "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana a las " . $date->format('h:i:s a')
        );
        
        if($code >= 200 && $code <= 300) {
            
            $headers = $this->waMsg->toInit();
            // Revisar si hay alguna cotizacion en curso
            $has = $this->fSys->hasCotizando($this->waMsg);
            if(!$has) {
                // Buscar en el cooler del cotizador que inicio sesion un item dispuesto
                $itemResult = $this->fSys->getNextBait($this->waMsg, '');
                file_put_contents('getNextBait_4.json', json_encode($itemResult));
                $wamid = '';
                if($itemResult['idAnet'] != 0) {
                    // TODO hacer todo para enviar $item
                    $headers = HeaderDto::idDB($headers, $itemResult['idAnet']);
                    $headers = HeaderDto::campoValor($headers, 'message', $wamid);
                    file_put_contents('getNextBait_5.json', json_encode($headers));
                }
            }else{
                // TODO... SE encontró un item pendiente de cotizar
                // Reenviarselo al cotizador para continuar su proceso
            }

            $this->waSender->sendMy($headers);
        }
    }

}
