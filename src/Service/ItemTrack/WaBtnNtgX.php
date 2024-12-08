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
            // Retornamos simplemente, ya que en el metodos existeInTrackeds()
            // ya enviamos los mensajes correspondientes al cotizador.
            return;
        }

        $item = [];
        if($hasCotInProgress) {
            
            $item = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
            if(count($item) > 0) {
                if($item['idAnet'] != $this->waMsg->idAnet) {
                    // Si se esta cotizando actualmente una, pero la que se dijo no tengo es otra
                    // entonces enviamos un mensaje de recordatorio que se esta en proceso de
                    // cotizacion de otra pieza.
                    $this->waSender->sendText(
                        "😉 *COTIZACIÓN EN PROGRESO*...\n".
                        "Actualmente estás cotizando otra autoparte:\n\n".
                        "Termina de cotizar la refacción anterior para poder ".
                        "continuar con a la siguiente cotización.\n\n".
                        "👍 _GRACIAS por tu atención_"
                    );
                    return;
                }
            }
        }
        
        if(count($item) == 0) {
            // Si no hay cotizacion en curso ponemos el item en la carpeta de tracking
            // como si estubiera cotizando, esto se hace por los siguientes motivos:
            // 1.- Eliminar el Item del cooler del cotizador y a su ves
            // 2.- Para que la clase HcFinisherCot, prosiga con el proceso de fin
            /// de cotizacion del item.
            $this->waSender->fSys->putCotizando($this->waMsg);
            $item = $this->waSender->fSys->getContent('tracking', $this->waMsg->from.'.json');
        }

        $finicher = new HcFinisherCot($this->waSender, $this->waMsg, $item);
        if(count($item) > 0) {
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
            'trackeds', $this->waMsg->idAnet.'_'.$this->waMsg->from.'.json'
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

}
