<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;

class HcCancelarCot
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
    }

    /** */
    private function createFilenameTmpOf(String $name): String {
        return $this->waMsg->from.'_'.$name.'.json';
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(String $filename): bool {
        return $this->fSys->existe('/', $filename);
    }

    /** */
    public function exe(): array
    {
        // Eliminamos el archivo indicativos
        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->createFilenameTmpOf($toDelete[$i]);
            if($this->isAtendido($filename)) {
                $this->fSys->delete('/', $filename);
            }
        }
        $this->fSys->delete('tracking', $this->bait['waId'].'.json');

        // Recuperamos otro bait directamente desde el estanque
        $this->waSender->setConmutador($this->waMsg);
        
        $otroBait = $this->fSys->getNextBait($this->waMsg, $this->bait['mdl']);
        if($otroBait != '') {
            $code = $this->waSender->sendTemplate($otroBait);
        }else {
            $code = $this->waSender->sendText(
                "📵 *Solicitud CANCELADA con éxito.*\n, por el momento no se encontró ".
                "otra cotización para ti, pero pronto te estarán llegando nuevas ".
                "oportunidades de venta.💰"
            );
        }
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($this->waMsg->toMini());
            return [];
        }

        return $this->bait;
    }
}
