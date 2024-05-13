<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;

class WaBtnCotNow
{
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
        $this->waSender->setConmutador($this->waMsg);
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(): bool { return $this->fSys->existe('/', $this->fileTmp); }

    /** */
    public function exe(bool $hasCotInProgress): void
    {
        if($this->existeInTrackeds()) {
            return;
        }

        $builder = new BuilderTemplates($this->fSys, $this->waMsg);
        if($hasCotInProgress) {
            // Abisamos que hay una cotizacion en progreso y damos opción a cancelar o seguir
            // con la que esta en curso.
            $bait = $this->fSys->getContent('tracking', $this->waMsg->from.'.json');
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
        $this->fSys->setContent('/', $this->fileTmp, ['']);
        
        $template = $builder->exe('sfto');
        $code = $this->waSender->sendPreTemplate($template);

        if($code >= 200 && $code <= 300 || $this->waMsg->isTest) {
            if($this->waSender->wamidMsg != '') {
                $this->waMsg->id = ($this->waMsg->context != '')
                    ? $this->waMsg->context : $this->waSender->wamidMsg;
            }
            $this->fSys->putCotizando($this->waMsg);
            $this->waSender->sendMy($this->waMsg->toMini());
        }
    }

    /**
     * Revisamos para ver si esta cotizacion ya fue cotizada por el mismo cotizador
     */
    private function existeInTrackeds(): bool
    {
        $exist = $this->fSys->getContent(
            'trackeds', $this->waMsg->from.'_'.$this->waMsg->idItem.'.json'
        );
        if(count($exist) > 0) {
            if($exist['wamid'] != '') {
                $this->waSender->context = $exist['wamid'];
            }
            $this->waSender->sendText(
                "😉👍 COTIZADA...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".count($exist['track']['fotos']).'*\n'.
                "Detalles: *".$exist['track']['detalles'].'*\n'.
                "Costo: *".$exist['track']['costo'].'*\n\n'.
                "_Pronto recibirás más oportunidades de venta_💰"
            );
        }
        return $exist;
    }
}
