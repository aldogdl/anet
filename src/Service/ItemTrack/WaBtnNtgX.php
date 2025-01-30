<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;

class WaBtnNtgX
{
    private WaMsgDto $waMsg;
    private WaSender $waSender;
    private array $item;

    /** */
    public function __construct(WaSender $waS, WaMsgDto $msg)
    {
        $this->waMsg     = $msg;
        $this->waSender  = $waS;
        $this->waSender->setConmutador($this->waMsg);
    }

    /** 
     * [V6]
    */
    public function exe(bool $hasCotInProgress)
    {
        $this->item = [];
        // Retornamos simplemente, ya que en el metodos existeInTrackeds() o existeInTracking()
        // ya enviamos los mensajes correspondientes al cotizador.
        if($this->existeInTrackeds()) { return; }
        if($hasCotInProgress) {
            if($this->existeInTracking()) { return; }            
        }
        
        if(count($this->item) == 0) {
            // Si no hay cotizacion en curso ponemos el item en la carpeta de tracking
            // como si estubiera cotizando, esto se hace por los siguientes motivos:
            // 1.- Eliminar el Item del cooler del cotizador y a su ves
            // 2.- Para que la clase HcFinisherCot, prosiga con el proceso de fin
            /// de cotizacion del item.
            $this->item = $this->waSender->fSys->putCotizando($this->waMsg, true);
        }

        $finicher = new HcFinisherCot($this->waSender, $this->waMsg, $this->item);
        if(count($this->item) > 0) {
            $finicher->exe($this->waMsg->subEvento);
        }else{
            // No se encontró una pieza en el cooler ni tampoco en trackeds(cotizada)
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
            'trackeds', $this->waMsg->idDbSr.'_'.$this->waMsg->from.'.json'
        );

        if(count($exist) > 0) {
            $resp = true;
            if(array_key_exists('wamid', $exist) && $exist['wamid'] != '') {
                $this->waSender->context = $exist['wamid'];
            }
            if(!array_key_exists('resp', $exist)) {
                return $resp;
            }
            $fotosCant = 0;
            if(array_key_exists('fotos', $exist['resp'])) {
                $fotosCant = count($exist['resp']['fotos']);
            }
            $this->waSender->sendText(
                "😉👍 *SIN EMBARGO*...\n".
                "Ya atendiste esta solicitud de cotización:\n\n".
                "No. de Fotos: *".$fotosCant."*\n".
                "Detalles: *".$exist['resp']['detalles']."*\n".
                "Costo: \$ *".$exist['resp']['costo']."*\n\n".
                "_Aún así GRACIAS por tu atención_"
            );
        }
        return $resp;
    }

    /**
     * Revisamos para ver si esta cotizacion ya fue cotizada por el mismo cotizador
     */
    private function existeInTracking(): bool
    {
        $this->item = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
        if(count($this->item) > 0) {
            if($this->item['idDbSr'] != $this->waMsg->idDbSr) {
                // Si se esta cotizando actualmente una, pero la que se dijo "no tengo" es otra,
                // entonces enviamos un mensaje de recordatorio que se esta en proceso de
                // cotizacion de otra pieza.
                $this->waSender->sendText(
                    "😉 *COTIZACIÓN EN PROGRESO*...\n".
                    "Actualmente estás cotizando otra autoparte:\n\n".
                    "Termina de cotizar la refacción anterior para poder ".
                    "continuar con la siguiente cotización.\n\n".
                    "👍 _GRACIAS por tu atención_"
                );
                return true;
            }
        }
        return false;
    }


}
