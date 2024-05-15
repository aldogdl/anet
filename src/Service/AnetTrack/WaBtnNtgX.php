<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\WaSender;

class WaBtnNtgX
{
    private WaMsgDto $waMsg;
    private WaSender $waSender;

    /** */
    public function __construct(WaSender $waS, WaMsgDto $msg)
    {
        $this->waMsg     = $msg;
        $this->waSender  = $waS;
        $this->waSender->setConmutador($this->waMsg);
    }

    /** */
    public function exe(bool $hasCotInProgress)
    {
        if($this->existeInTrackeds()) {
            return;
        }

        $bait = [];
        if($hasCotInProgress) {
            $bait = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
            if(count($bait) > 0) {
                // Si se esta cotizando actualmente una, pero la que se dijo no tengo es otra
                // entonces no la tomamos en cuenta reiniciando la var bait
                if($bait['idItem'] != $this->waMsg->idItem) {
                    $bait = [];
                }
            }
        }
        
        // Si no hay cotizacion en curso ponemos el bait como si estuviera cotizando
        if(count($bait) == 0) {
            $this->waSender->fSys->putCotizando($this->waMsg);
            $bait = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
        }

        if(count($bait) > 0) {
            $finicher = new HcFinisherCot($this->waSender, $this->waMsg, $bait);
            $finicher->exe($this->waMsg->subEvento);
            return;
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
                "😉👍 *SIN EMBARGO*...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".count($exist['track']['fotos'])."*\n".
                "Detalles: *".$exist['track']['detalles']."*\n".
                "Costo: \$ *".$exist['track']['costo']."*\n\n".
                "_Aún así GRACIAS por tu atención_"
            );
        }
        return $resp;
    }
}
