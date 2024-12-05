<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;

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
                // entonces enviamos un mensaje de recordatorio que se esta en proceso de
                // cotizacion de otra pieza.
                if($bait['idItem'] != $this->waMsg->idItem) {
                    $this->waSender->sendText(
                        "😉 *COTIZACIÓN EN PROGRESO*...\n".
                        "Actualmente estás cotizando otra autoparte:\n\n".
                        "Termina de cotizar respondiendo al último mensaje\n".
                        "y podrás continuar respondiendo a la siguiente.\n\n".
                        "👍 _GRACIAS por tu atención_"
                    );
                    return;
                }
            }
        }
        
        // Si no hay cotizacion en curso ponemos el bait como si estuviera cotizando
        if(count($bait) == 0) {
            $this->waSender->fSys->putCotizando($this->waMsg);
            $bait = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
        }

        $finicher = new HcFinisherCot($this->waSender, $this->waMsg, $bait);
        if(count($bait) > 0) {
            $finicher->exe($this->waMsg->subEvento);
        }else{
            // No se encontró una pieza en trackeds(cotizada) ni tampoco en el cooler
            $finicher->exe('checkNt');
        }
        return;
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
            if(!array_key_exists('track', $exist)) {
                return $resp;
            }
            $fotosCant = 0;
            if(array_key_exists('fotos', $exist['track'])) {
                $fotosCant = count($exist['track']['fotos']);
            }
            $this->waSender->sendText(
                "😉👍 *SIN EMBARGO*...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".$fotosCant."*\n".
                "Detalles: *".$exist['track']['detalles']."*\n".
                "Costo: \$ *".$exist['track']['costo']."*\n\n".
                "_Aún así GRACIAS por tu atención_"
            );
        }
        return $resp;
    }

}
