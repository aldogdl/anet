<?php

namespace App\Service\AnetTrack;

use App\Dtos\HeaderDto;
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
        $headers = $this->waMsg->toStt(true);

        $result = $this->responseToAction($tipoFinish);
        $headers = HeaderDto::campoValor($headers, 'baits', $result['baitsInCooler']);
        if($result['send'] != '') {
            $headers = HeaderDto::campoValor($headers, 'sended', $result['send']);
        }
        $headers = HeaderDto::event($headers, $this->waMsg->subEvento);
        $headers = HeaderDto::idItem($headers, $this->waMsg->idItem);
        
        if($result['code'] >= 200 && $result['code'] <= 300 || $this->waMsg->isTest) {

            $response = ['header' => $headers];
            if($tipoFinish == 'fin') {
                // Incluimos todos los datos resultantes de la cotizacion y sus cabecesaras
                $headers = HeaderDto::includeBody($headers, true);
                $response = [$this->bait['track'], 'header' => $headers];
            }

            if($this->waMsg->subEvento == 'cleanCN' || $this->waMsg->subEvento == 'cleanNt') {
                $resumen = $this->waSender->fSys->getResumeCooler($this->waMsg->from);
                $response = [$resumen, 'header' => $headers];
                $headers = HeaderDto::includeBody($headers, true);
            }

            if(mb_strpos($this->waMsg->idItem, 'demo') === false) {
                $this->waSender->sendMy($response);
            }
        }

        // Eliminamos los archivos que indican el paso de cotizacion actual.
        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->createFilenameTmpOf($toDelete[$i]);
            $this->waSender->fSys->delete('/', $filename);
        }

        // Al finalizar eliminamos el archivo que detiene los status
        // no es necesario mantenerlo ya que no sabemos si el cotizador
        // va a continuar cotizando la siguiente solicitud.
        $this->waSender->fSys->delete('/', $this->createFilenameTmpOf('stopstt'));

    }

    /** */
    private function responseToAction(String $tipoFinish): array
    {
        $mrk = (count($this->bait) > 0) ? $this->bait['mrk'] : '';
        // Dependiendo de la accion realizada grabamos distintas
        // cabeceras para la respuesta a dicha accion.
        if($tipoFinish == 'cancel') {
            $head = "📵 *Solicitud CANCELADA.*\n\n";
        }elseif($tipoFinish == 'ntg') {
            $head = "🙂👍 *NO TE PREOCUPES.*\n\n";
            $this->waMsg->subEvento = 'ntg';
            $this->bait['track'] = ['fotos' => [], 'detalles' => 'No Tengo Pieza', 'costo' => 0];
        }elseif($tipoFinish == 'ntga') {
            $head = "🚗 *PERFECTO GRACIAS.*\n\n";
            $this->waMsg->subEvento = 'ntga';
            $this->bait['track'] = ['fotos' => [], 'detalles' => 'No Vendo la Marca', 'costo' => 0];
        }elseif($tipoFinish == 'checkCnow') {
            $head = "😉 *SOLICITUD RECIBIDA PARA COTIZAR!!*...\n\n";
            $this->waMsg->subEvento = 'cleanCN';
        }elseif($tipoFinish == 'checkNt') {
            $head = "😉 *OK, ENTERADOS*...\n\n";
            $this->waMsg->subEvento = 'cleanNt';
        }else {
            $head = "😃🫵 *Sigue vendiendo MÁS!!.*\n\n";
            $this->waMsg->subEvento = 'sgrx';
            $this->waMsg->idItem = $this->bait['idItem'];
        }

        $body = "Aquí tienes otra oportunidad de venta💰";
        
        // Si el subEvent se coloco en cleaner es que no hay un bait en el cooler del cotizador
        // y tampoco se encontro en trackeds, por lo tanto el objetivo es enviar msg a comCore
        // para que limpie tambien los datos en SL en caso de inconcistencia.
        $baitFromCooler = ['send' => ''];
        $idDemo = (mb_strpos($this->waMsg->idItem, 'demo') === false) ? false : true;

        if(mb_strpos($this->waMsg->subEvento, 'clean') === false && !$idDemo) {
            $this->waSender->fSys->setContent('trackeds', $this->bait['idItem']."_".$this->bait['waId'].'.json', $this->bait);
            $this->waSender->fSys->delete('tracking', $this->bait['waId'].'.json');
            // Recuperamos otro bait directamente desde el estanque
            $baitFromCooler = $this->waSender->fSys->getNextBait($this->waMsg, $mrk);
        }else if($idDemo) {
            $this->waSender->fSys->delete('tracking', $this->bait['waId'].'.json');
        }

        // Quitamos el context para que los msg siguientes no
        // lleven la cabecera de la cotizacion en curso.
        $contextOld = $this->waSender->context;
        $this->waSender->context = '';
        // Si se encotro un nuevo bait, el idItem esta en el campo send
        // por ende enviamos el nuevo bait al cotizador
        $code = 200;
        if($baitFromCooler['send'] != '') {
            $code = $this->waSender->sendText($head.$body);
            $code = $this->waSender->sendTemplate($baitFromCooler['send']);
        }else {
            // Si no se encontro un nuevo bait se analizan los siguiente aspecto y se
            // actua en concecuencia.
            if($this->waMsg->subEvento == 'cleanCN') {
                $body = "El sistema automatizado esta organizando ".
                "tus oportunidades de venta 💰, *danos 5 segundos*".
                "para continuar con tu solicitud. ¡Éxito!";
            }elseif($this->waMsg->subEvento == 'cleanNt') {
                $body = "Hemos recibido tu indicación\n\n".
                "👍 _GRACIAS por tu atención_";
            }else{
                $body = "Por el momento no se encontró ".
                "otra cotización para ti, pero pronto te estarán llegando nuevas ".
                "oportunidades de venta.💰 ¡Éxito!";
            }

            if($tipoFinish == 'fin') {
                $body = "";
            }

            $code = $this->waSender->sendText($head.$body);
        }

        // Volvemos a colocar el contexto para proceguir con el proceso siguiente
        $this->waSender->context = $contextOld;
        $baitFromCooler['code'] = $code;
        return $baitFromCooler;
    }
}
