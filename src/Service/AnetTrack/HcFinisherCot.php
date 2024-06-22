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
        }elseif($tipoFinish == 'checkCnow') {
            $head = "😉 *COTIZAR AHORA!!*...\n\n";
        }elseif($tipoFinish == 'checkNt') {
            $head = "😉 *OK, ENTERADOS*...\n\n";
        }else {
            $head = "😃🫵 *Sigue vendiendo MÁS!!.*\n\n";
        }
        $body = "Aquí tienes otra oportunidad de venta💰";
        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        
        $model = (count($this->bait) > 0) ? $this->bait['mdl'] : '';
        $track = (count($this->bait) > 0) ? $this->bait['track'] : [];
        
        if($tipoFinish == 'ntg') {

            $this->waMsg->subEvento = 'ntg';
            $track = ['fotos' => [], 'detalles' => 'No Tengo Pieza', 'costo' => 0];
            $this->bait['track'] = $track;

        }elseif($tipoFinish == 'ntga') {

            $this->waMsg->subEvento = 'ntga';
            $track = ['fotos' => [], 'detalles' => 'No Tengo Auto', 'costo' => 0];
            $this->bait['track'] = $track;
            $model = '';

        }elseif($tipoFinish == 'checkCnow') {

            $this->waMsg->subEvento = 'cleaner';
            $body = "Al parecer esta solicitud ya\n".
            "no está disponible, pero pronto recibirás\n".
            "*más oportunidades de Venta*\n\n".
            "👍 _GRACIAS por tu atención_";

        }elseif($tipoFinish == 'checkNt') {

            $this->waMsg->subEvento = 'cleaner';
            $body = "Hemos recibido tu indicación\n\n".
            "👍 _GRACIAS por tu atención_";

        }elseif($tipoFinish == 'fin') {

            $this->waMsg->subEvento = 'sgrx';
            $this->waMsg->idItem = $this->bait['idItem'];

        }

        $att = $this->waMsg->toMini();
        $track['idCot'] = time();
        $att['body'] = $track;
        $baitsCooler = ['send' => ''];

        // Si el subEvent se coloco en cleaner es que no hay un bait en el cooler del cotizador
        // y tampoco se encontro en trackeds, por lo tanto el objetivo es enviar msg a comCore
        // para que limpie tambien los datos en SL en caso de inconcistencia.
        if($this->waMsg->subEvento != 'cleaner') {
            $this->waSender->fSys->setContent('trackeds', $this->bait['idItem']."_".$this->bait['waId'].'.json', $this->bait);
            $this->waSender->fSys->delete('tracking', $this->bait['waId'].'.json');
            
            // Recuperamos otro bait directamente desde el estanque
            $baitsCooler = $this->waSender->fSys->getNextBait($this->waMsg, $model);
        }

        // Quitamos el context para que los msg siguientes no
        // lleven la cabecera de la cotizacion en curso.
        $this->waSender->context = '';
        
        // Si se encotro un nuevo bait, el idItem esta en el campo send
        // por ende enviamos el nuevo bait al cotizador
        $code = 200;
        if($baitsCooler['send'] != '') {

            $code = $this->waSender->sendText($head.$body);
            $code = $this->waSender->sendTemplate($baitsCooler['send']);
        }else {

            // Si no se encontro un nuevo bait se analizan los siguiente aspecto y se
            // actua en concecuencia.

            if($this->waMsg->subEvento != 'cleaner') {
                $body = "Por el momento no se encontró ".
                "otra cotización para ti, pero pronto te estarán llegando nuevas ".
                "oportunidades de venta.💰 ¡Éxito!";
            }

            if($tipoFinish == 'fin') {
                $body = "📖 Mañana a primera hora ésta cotización estará ".
                "publicada en tu catálogo digital_ *AnetShop*.";
            }

            $code = $this->waSender->sendText($head.$body);
        }

        // Volvemos a colocar el contexto para proceguir con el proceso siguiente
        if(count($this->bait) > 0) {
            $this->waSender->context = $this->bait['wamid'];
        }

        if($baitsCooler['send'] != '') {
            $att['send'] = $baitsCooler['send'];
        }

        $att['baitsInCooler'] = (array_key_exists('baitsInCooler', $baitsCooler))
            ? $baitsCooler['baitsInCooler'] : [];
        
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            $this->waSender->sendMy($att);
        }

        // Eliminamos los archivos que indican el paso de cotizacion actual.
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->createFilenameTmpOf($toDelete[$i]);
            $this->waSender->fSys->delete('/', $filename);
        }

        // Al finalizar eliminamos el archivo que detiene los status
        // no es necesario mantenerlo ya que no sabemos si el cotizador
        // va a continuar cotizando la siguiente solicitud.
        $filename = $this->createFilenameTmpOf('stopstt');
        $this->waSender->fSys->delete('/', $filename);

    }

}
