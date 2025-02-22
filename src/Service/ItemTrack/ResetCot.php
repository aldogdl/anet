<?php

namespace App\Service\ItemTrack;

use App\Service\MyFsys;

class ResetCot
{
    private String $idDbSr;
    private String $waIdCot;
    private MyFsys $fSys;

    /** */
    public function __construct(MyFsys $fSys, String $idDbSr, String $waIdCot)
    {
        $this->idDbSr = $idDbSr;
        $this->waIdCot = $waIdCot;
        $this->fSys = $fSys;
    }

    /**
     * [V6]
     * Reseteamos la cotizacion en curso o la que se encuentra en cotizadas
     * borrando su cotizacion anterior para volvarle a enviar el mensaje al
     * cotizador y que buelva a repetir la acción.
     */
    public function exe(): String
    {
        $folder = 'trackeds';
        $filename = $this->idDbSr."_".$this->waIdCot;

        $cotizada = $this->fSys->getContent($folder, $filename.'.json');
        if(count($cotizada) == 0) {
            $folder = 'tracking';
            $filename = $this->waIdCot;
            // Si no se encunetra en trackeds buscamos la cotizacion en curso
            $cotizada = $this->fSys->getContent($folder, $filename.'.json');
        }
        if(count($cotizada) == 0) {
            return 'X La cotización solicitada no existe en Atendidas';
        }
        $this->fSys->delete($folder, $filename.'.json');

        // El item encontrado, lo limpiamos para que quede como nuevo
        if(array_key_exists('wamid', $cotizada)) {
            unset($cotizada['wamid']);
        }
        if(array_key_exists('current', $cotizada)) {
            unset($cotizada['current']);
        }
        if(array_key_exists('attend', $cotizada)) {
            unset($cotizada['attend']);
        }
        if(array_key_exists('resp', $cotizada)) {
            unset($cotizada['resp']);
        }

        // Recuperamos la hielera del cotizador
        $cooler = $this->fSys->getContent('coolers', $this->waIdCot.'.json');
        // Buscamos en cooler para ver si existe la solicitud
        $has = array_search($this->idDbSr, array_column($cooler, 'idDbSr'));
        if($has !== false) {
            // Si existe lo eliminamos para insertar la que se encontro en Trackeds
            unset($cooler[$has]);
        }

        array_unshift($cooler, $cotizada);
        $this->fSys->setContent('coolers', $this->waIdCot.'.json', $cooler);
        
        // Borramos tambien los registros de sendmy para deshacernos de basura
        $this->fSys->deleteSendmyFiles($this->idDbSr, $this->waIdCot);

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