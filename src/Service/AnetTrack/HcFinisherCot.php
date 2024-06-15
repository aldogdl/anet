<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\WaSender;

class HcFinisherCot
{
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    
    /** */
    public function __construct(WaSender $waS, WaMsgDto $msg, array $bait)
    {
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->bait = $bait;
        $this->waSender->setConmutador($this->waMsg);
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
        return $this->waSender->fSys->existe('/', $filename);
    }

    /** */
    public function exe(String $tipoFinish = 'cancel'): void
    {
        // Dependiendo de la accion realizada grabamos distintas
        // cabeceras para la respuesta a dicha accion.
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
        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        
        $model = $this->bait['mdl'];
        $track = $this->bait['track'];
        
        if($tipoFinish == 'ntg') {
            $this->waMsg->subEvento = 'ntg';
            $this->bait['track'] = ['fotos' => [], 'detalles' => 'No Tengo Pieza', 'costo' => 0];
        }elseif($tipoFinish == 'ntga') {
            $this->waMsg->subEvento = 'ntga';
            $this->bait['track'] = ['fotos' => [], 'detalles' => 'No Tengo Auto', 'costo' => 0];
            $model = '';
        }elseif($tipoFinish == 'fin') {
            $this->waMsg->subEvento = 'sgrx';
            $this->waMsg->idItem = $this->bait['idItem'];
        }

        $att = $this->waMsg->toMini();
        if($tipoFinish == 'fin') {
            $track['idCot'] = time();
            $att['body'] = $track;
        }

        $this->waSender->fSys->setContent('trackeds', $this->bait['idItem']."_".$this->bait['waId'].'.json', $this->bait);
        $this->waSender->fSys->delete('tracking', $this->bait['waId'].'.json');
        
        // Recuperamos otro bait directamente desde el estanque
        $baitsCooler = $this->waSender->fSys->getNextBait($this->waMsg, $model);
        
        $this->waSender->context = $this->bait['wamid'];
        file_put_contents('wa_hallada.json', json_encode($baitsCooler));
        
        if($baitsCooler['send'] != '') {
            $code = $this->waSender->sendText($head.$body);
            $code = $this->waSender->sendTemplate($baitsCooler['send']);
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

        if($baitsCooler['send'] != '') {
            $att['send'] = $baitsCooler['send'];
        }
        $att['baitsInCooler'] = $baitsCooler['baitsInCooler'];
        
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($att);
        }

        // Eliminamos los archivos que indican el paso de cotizacion actual.
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->createFilenameTmpOf($toDelete[$i]);
            $this->waSender->fSys->delete('/', $filename);
        }
    }

}
