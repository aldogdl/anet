<?php

namespace App\Service\ItemTrack;

use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;
use App\Service\DemoSol\DemoSol;

class WaBtnCotNow
{
    private WaMsgDto $waMsg;
    private WaSender $waSender;
    private String $fileTmp = '';

    /** */
    public function __construct(WaSender $waS, WaMsgDto $msg)
    {
        $this->waMsg     = $msg;
        $this->waSender  = $waS;
        $this->fileTmp   = $this->waMsg->from.'_'.$this->waMsg->subEvento.'.json';
        $this->waSender->setConmutador($this->waMsg);
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(): bool { return $this->waSender->fSys->existe('/', $this->fileTmp); }

    /** 
     * [V6]
    */
    public function exe(bool $hasCotInProgress): void
    {
        if($this->existeInTrackeds()) { return; }

        $builder = new BuilderTemplates($this->waSender->fSys, $this->waMsg);
        if($hasCotInProgress) {
            // Avisamos que hay una cotizacion en progreso y damos opción a cancelar o seguir
            // con la que esta en curso.
            $item = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
            if(count($item) > 0) {
                $template = $builder->exe('cext', $item['idDbSr']);
                if(array_key_exists('wamid', $item)) {
                    $this->waSender->context = $item['wamid'];
                }
                $this->waSender->sendPreTemplate($template);
            }
            return;
        }

        if($this->isAtendido()) {
            return;
        }
        $isDemo = (mb_strpos($this->waMsg->content, 'demo') === false) ? false : true;

        $exite = true;
        if(!$isDemo) {
            $exite = $this->waSender->fSys->existeInCooler($this->waMsg->from, $this->waMsg->idDbSr);
        }

        if(!$exite) {
            $finicher = new HcFinisherCot($this->waSender, $this->waMsg, []);
            // No se encontró una pieza en trackeds(cotizada) ni tampoco en el cooler
            $finicher->exe('checkCnow');
            return;
        }

        // Con este archivo detenemos todos los mensajes de status
        $this->waSender->fSys->setStopStt($this->waMsg->from);

        $template = $builder->exe('sfto');
        $code = $this->waSender->sendPreTemplate($template);

        if($code >= 200 && $code <= 300) {

            if($this->waSender->wamidMsg != '') {
                $this->waMsg->id = ($this->waMsg->context != '')
                ? $this->waMsg->context : $this->waSender->wamidMsg;
            }

            if($isDemo) {
                $demo = new DemoSol($this->waSender->fSys);
                $baitDemo = $demo->buildBaitDemo($this->waMsg);
                $this->waSender->fSys->setContent('tracking', $this->waMsg->from.'.json', $baitDemo);
                return;
            }

            $this->waSender->fSys->putCotizando($this->waMsg);
            // Tomamos las cabeceras básicas para enviar a ComCore
            $headers = $this->waMsg->toStt(true);
            // Grabamos el valor de la accion
            $headers = HeaderDto::setValue($headers, 'sfto');
            if(!$this->waMsg->isTest) {
                $this->waSender->sendMy(['header' => $headers]);
            }
        }
    }

    /**
     * Revisamos para ver si esta cotizacion ya fue cotizada por el mismo cotizador
     */
    private function existeInTrackeds(): bool
    {
        $resp = false;
        $exist = $this->waSender->fSys->getContent(
            'trackeds', $this->waMsg->idDbSr.'_'.$this->waMsg->from.'.json'
        );

        if(count($exist) > 0) {
            $resp = true;
            if($exist['wamid'] != '') {
                $this->waSender->context = $exist['wamid'];
            }
            $this->waSender->sendText(
                "😉👍 *PERDON PERO*...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".count($exist['resp']['fotos'])."*\n".
                "Detalles: *".$exist['resp']['detalles']."*\n".
                "Costo: \$ *".$exist['resp']['costo']."*\n\n".
                "_Pronto recibirás más oportunidades de venta_💰"
            );
        }
        return $resp;
    }

}
