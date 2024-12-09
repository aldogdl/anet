<?php

namespace App\Service\ItemTrack;

use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;

class HcFinisherCot
{
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $item;

    /** */
    public function __construct(WaSender $waS, WaMsgDto $msg, array $theItem)
    {
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->item = $theItem;
        if($this->waMsg->idAnet == '') {
            $this->waMsg->idAnet = $this->item['idAnet'];
        }
        $this->waSender->setConmutador($this->waMsg);
    }

    /** 
     * [V6]
    */
    public function exe(String $tipoFinish = 'cancel'): void
    {
        $headers = $this->waMsg->toStt(true);

        $result = $this->responseToAction($tipoFinish);
        $cant = 0;
        if(array_key_exists('cant', $result)) {
            $cant = $result['cant'];
        }
        
        if($result['idAnet'] > 0) {
            $headers = HeaderDto::sendedidAnet($headers, $result['idAnet']);
            $headers = HeaderDto::sendedWamid($headers, $result['wamid']);
        }
        $headers = HeaderDto::event($headers, $this->waMsg->subEvento);
        $headers = HeaderDto::idDB($headers, $this->waMsg->idAnet);
        
        if($result['code'] >= 200 && $result['code'] <= 300 || $this->waMsg->isTest) {

            $response = ['header' => $headers];
            if($tipoFinish == 'fin') {
                // Incluimos todos los datos resultantes de la cotizacion y sus cabecesaras
                $headers = HeaderDto::includeBody($headers, true);
                $response = [$this->item['resp'], 'header' => $headers];
            }

            if($this->waMsg->subEvento == 'cleanCN' || $this->waMsg->subEvento == 'cleanNt') {
                $resumen = $this->waSender->fSys->getResumeCooler($this->waMsg->from);
                $response = [$resumen, 'header' => $headers];
                $headers = HeaderDto::includeBody($headers, true);
            }

            // Si no es demo, enviamos el resultado a AnetTrack
            if(mb_strpos($this->waMsg->idAnet, 'demo') === false) {
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
        // Dependiendo de la accion realizada grabamos distintas
        // cabeceras para la respuesta a dicha accion.
        if($tipoFinish == 'cancel') {
            $head = "📵 *Solicitud CANCELADA.*\n\n";
        }elseif($tipoFinish == 'ntg') {
            $head = "🙂👍 *NO TE PREOCUPES.*\n\n";
            $this->waMsg->subEvento = 'ntg';
            $this->item['resp'] = ['fotos' => [], 'detalles' => 'No Tengo Pieza', 'costo' => 0];
        }elseif($tipoFinish == 'ntga') {
            $head = "🚗 *PERFECTO GRACIAS.*\n\n";
            $this->waMsg->subEvento = 'ntga';
            $this->item['resp'] = ['fotos' => [], 'detalles' => 'No Vendo la Marca', 'costo' => 0];
        }elseif($tipoFinish == 'checkCnow') {
            $head = "😉 *SOLICITUD RECIBIDA PARA COTIZAR!!*...\n\n";
            $this->waMsg->subEvento = 'cleanCN';
        }elseif($tipoFinish == 'checkNt') {
            $head = "😉 *OK, ENTERADOS*...\n\n";
            $this->waMsg->subEvento = 'cleanNt';
        }else {
            $head = "😃🫵 *Sigue vendiendo MÁS!!.*\n\n";
            $this->waMsg->subEvento = 'sgrx';
        }

        $body = "Aquí tienes otra oportunidad de venta💰";
        
        // Si el subEvent se coloco en cleaner es que no hay un item en el cooler del cotizador
        // y tampoco se encontro en trackeds, por lo tanto el objetivo es enviar msg a comCore
        // para que limpie tambien los datos en SL en caso de inconcistencia.
        $itemFromCooler = ['idAnet' => 0, 'cant' => 0];
        $idDemo = (mb_strpos($this->waMsg->idAnet, 'demo') === false) ? false : true;
        
        if(mb_strpos($this->waMsg->subEvento, 'clean') === false && !$idDemo) {
            
            // Si no es subEvento "clean" y tampoco es "DEMO"
            $this->waSender->fSys->setContent('trackeds', $this->item['idAnet']."_".$this->item['waId'].'.json', $this->item);
            $this->waSender->fSys->delete('tracking', $this->item['waId'].'.json');
            // Recuperamos otro item directamente desde el cooler del cotizador
            $mrk = (count($this->item) > 0) ? $this->item['mrk'] : '';
            $itemFromCooler = $this->waSender->fSys->getNextItemForSend($this->waMsg, $mrk);

        }else if($idDemo) {
            $this->waSender->fSys->delete('tracking', $this->item['waId'].'.json');
        }

        // Quitamos el context para que los msg siguientes no
        // lleven la cabecera de la cotizacion en curso.
        $contextOld = $this->waSender->context;
        $this->waSender->context = '';

        // Si se encontró un nuevo item, enviamos el nuevo item al cotizador
        $code = 200;
        if($itemFromCooler['cant'] > 0) {
            $code = $this->waSender->sendText($head.$body);
            $code = $this->waSender->sendTemplate($itemFromCooler['idAnet']);
            if($code == 200) {
                $itemFromCooler['wamid'] = $this->waSender->wamidMsg;
            }
        }else {
            // Si no se encontro un nuevo item se analizan los siguiente aspecto y se
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
        $itemFromCooler['code'] = $code;
        return $itemFromCooler;
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

}
