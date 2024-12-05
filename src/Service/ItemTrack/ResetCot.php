<?php

namespace App\Service\ItemTrack;

use App\Service\ItemTrack\Fsys;

class ResetCot
{
    private String $idItem;
    private String $waIdCot;
    private Fsys $fSys;

    /** */
    public function __construct(Fsys $fSys, String $idItem, String $waIdCot)
    {
        $this->idItem = $idItem;
        $this->waIdCot = $waIdCot;
        $this->fSys = $fSys;
    }

    /**
     * Reseteamos la cotizacion en curso o la que se encuentra en cotizadas
     * borrando su cotizacion anterior para volvarle a enviar el mensaje al
     * cotizador y que buelva a repetir la acción.
     */
    public function exe(): String
    {
        $folder = 'trackeds';
        $filename = $this->idItem."_".$this->waIdCot;

        $cotizada = $this->fSys->getContent($folder, $filename.'.json');
        if(count($cotizada) == 0) {
            $folder = 'tracking';
            $filename = $this->waIdCot;
            // Si no se encunetra en trackeds buscamos la cotizacion en curso
            $cotizada = $this->fSys->getContent($folder, $filename.'.json');
            if(count($cotizada) == 0) {
                return 'X La cotización solicitada no existe en Atendidas';
            }
        }
        
        $this->fSys->delete($folder, $filename.'.json');

        // Recuperamos la hielera del cotizador
        $cooler = $this->fSys->getContent('waEstanque', $this->waIdCot.'.json');
        if(count($cooler) == 0) {
            return 'X El cooler del cotizador no existe en SR.';
        }

        $items = $cooler['items'];
        // Buscamos en cooler para ver si existe la solicitud
        $has = array_search($this->idItem, array_column($items, 'idItems'));
        if($has !== false) {
            // Si existe lo eliminamos para insertar la que se encontro en Trackeds
            unset($items[$has]);
        }

        // Limpiamos la cotizacion
        $cotizada['track'] = [];
        array_unshift($items, $cotizada);
        $cooler['items'] = $items;

        $this->fSys->setContent('waEstanque', $this->waIdCot.'.json', $cooler);

        $toDelete = ['cnow', 'sfto', 'sdta', 'scto'];
        $rota = count($toDelete);
        for ($i=0; $i < $rota; $i++) { 
            $filename = $this->waIdCot.'_'.$toDelete[$i].'.json';
            $this->fSys->delete('/', $filename);
        }
        $this->fSys->delete('/', $this->waIdCot."_stopstt.json");
        return 'ok';
    }
}