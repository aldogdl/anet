<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;

class HcFinisherCot
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    
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
    public function exe(String $tipoFinish = 'cancel'): void
    {
        // Eliminamos los residuos de los archivos indicativos del proceso de Cot.
        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->createFilenameTmpOf($toDelete[$i]);
            if($this->isAtendido($filename)) {
                $this->fSys->delete('/', $filename);
            }
        }
        
        $this->fSys->setContent('trackeds', $this->bait['waId']."_".$this->bait['idItem'].'.json', $this->bait);
        $this->fSys->delete('tracking', $this->bait['waId'].'.json');
        
        $this->waSender->setConmutador($this->waMsg);

        // Dependiendo de la accion realizada grabamos distintas
        // cabeceras para la respuesta a dicha accion
        if($tipoFinish == 'cancel') {
            $head = "📵 *Solicitud CANCELADA.*\n\n";
        }elseif($tipoFinish == 'ntg') {
            $head = "🙂👍 *NO TE PREOCUPES.*\n\n";
        }elseif($tipoFinish == 'ntga') {
            $head = "🚗 *PERFECTO GRACIAS.*\n\n";
        }else {
            $head = "😃🫵 *Sigue vendiendo MÁS!!.*\n\n";
        }
        $body = "Aquí tienes otra oportunidad de venta💰";

        // Tomamos el mensaje que fué atendido
        $att = ($tipoFinish == 'fin') ? $this->bait['track'] : $this->waMsg->toMini();

        // Recuperamos otro bait directamente desde el estanque
        $otroBait = $this->fSys->getNextBait($this->waMsg, $this->bait['mdl']);
        
        $this->waSender->context = $this->bait['wamid'];
        if($otroBait != '') {
            $code = $this->waSender->sendText($head.$body);
            $code = $this->waSender->sendTemplate($otroBait);
        }else {

            $body = "Por el momento no se encontró ".
            "otra cotización para ti, pero pronto te estarán llegando nuevas ".
            "oportunidades de venta.💰 ¡Éxito!";

            if($tipoFinish == 'fin') {
                $body = "📖 Mañana a primera hora ésta cotización estará ".
                "publicada en tu catálogo digital_ *AnetShop*.";
            }

            $code = $this->waSender->sendText($head.$body);
        }

        $retornar = ['evento' => 'whatsapp_api', 'payload' => ['att' => $att, 'send' => $otroBait]];
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($retornar);
        }
    }

}
