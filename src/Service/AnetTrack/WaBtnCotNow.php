<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\WaSender;

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

    /** */
    public function exe(bool $hasCotInProgress): void
    {
        if($this->existeInTrackeds()) {
            return;
        }
        
        $builder = new BuilderTemplates($this->waSender->fSys, $this->waMsg);
        if($hasCotInProgress) {
            // Avisamos que hay una cotizacion en progreso y damos opción a cancelar o seguir
            // con la que esta en curso.
            $bait = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
            if(count($bait) > 0) {
                $template = $builder->exe('cext', $bait['idItem']);
                if(array_key_exists('wamid', $bait)) {
                    $this->waSender->context = $bait['wamid'];
                }
                $code = $this->waSender->sendPreTemplate($template);
                if($code >= 200 && $code <= 300) {
                    $this->waSender->sendMy($this->waMsg->toMini());
                }
            }
            return;
        }

        if($this->isAtendido()) {
            return;
        }

        $exite = $this->waSender->fSys->existeInCooler($this->waMsg->from, $this->waMsg->idItem);
        if(!$exite) {
            $finicher = new HcFinisherCot($this->waSender, $this->waMsg, []);
            // No se encontró una pieza en trackeds(cotizada) ni tampoco en el cooler
            $finicher->exe('checkCnow');
            return;
        }

        // Con este archivo detenemos todos los mensajes de status
        $this->waSender->fSys->setContent('/', $this->fileTmp, ['']);
        
        $template = $builder->exe('sfto');
        $code = $this->waSender->sendPreTemplate($template);
        
        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            if($this->waSender->wamidMsg != '') {
                $this->waMsg->id = ($this->waMsg->context != '')
                ? $this->waMsg->context : $this->waSender->wamidMsg;
            }
            $this->waSender->fSys->putCotizando($this->waMsg);
            $this->waSender->sendMy($this->waMsg->toMini());
        }
    }

    /**
     * Revisamos para ver si esta cotizacion ya fue cotizada por el mismo cotizador
     */
    private function existeInTrackeds(): bool
    {
        $resp = false;
        $exist = $this->waSender->fSys->getContent(
            'trackeds', $this->waMsg->idItem.'_'.$this->waMsg->from.'.json'
        );

        if(count($exist) > 0) {
            $resp = true;
            if($exist['wamid'] != '') {
                $this->waSender->context = $exist['wamid'];
            }
            $this->waSender->sendText(
                "😉👍 *PERDON PERO*...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".count($exist['track']['fotos'])."*\n".
                "Detalles: *".$exist['track']['detalles']."*\n".
                "Costo: \$ *".$exist['track']['costo']."*\n\n".
                "_Pronto recibirás más oportunidades de venta_💰"
            );
        }
        return $resp;
    }
}
