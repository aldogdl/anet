<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WapiProcess\FsysProcess;

class TrackFileCot {

    public FsysProcess $fSys;
    private WaMsgMdl $message;
    private array $paths;
    
    public bool $hasItems = false;
    // Revisamos que el item que se respondio no exista entre los atendidos
    public bool $isAtendido = false;
    // El archivo completo del trackFile del cotizador, se manipulara y se actualizara
    public array $trackFile = [];
    // El item el cual fue respondido por medio de los botones
    public $itemCurrentResponsed = [];
    // Los items que se enviarán al archivo trackeds de atendidos.
    private $itemsToTrackeds = [];
    // El index donde se encontró el items que se esta atendiendo.
    private $itemCurrentIndx = [];

    /** 
     * Tomamos el trackFile del cotizador.
     * Buscamos el item respondido y lo pasamos a itemsToTrackeds.
     * A su ves lo eliminamos de la lista de trackFile
    */
    public function __construct(WaMsgMdl $message, array $paths)
    {
        $this->paths = $paths;
        $this->message = $message;
        
        $this->isAtendido = false;
        $path = $this->paths['trackeds'].'/'.$this->message->from.'.json';
        if(is_file($path)) {
            $trakeds = json_decode(file_get_contents($path), true);
            if(in_array($this->message->message['idItem'], $trakeds)) {
                $this->isAtendido = true;
            }
        }
    }

    /** 
     * Tomamos el trackFile del cotizador.
     * Buscamos el item respondido y lo pasamos a itemsToTrackeds.
     * A su ves lo eliminamos de la lista de trackFile
    */
    public function sabe()
    {

        // Tomamos el archivo TrackFile
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->trackFile = $this->fSys->getTrackFileOf($this->message->from);
        
        if(count($this->trackFile) > 0) {
            
            if(count($this->trackFile['items']) > 0) {
                
                $this->hasItems = true;
                // 1.- Tomamos el item que disparo este evento (el respondido por un boton)
                $idsItems = array_column($this->trackFile['items'], 'idItem');
                $this->itemCurrentIndx = array_search($this->message->message['idItem'], $idsItems);
                
                if($this->itemCurrentIndx !== false) {
                    $this->itemCurrentResponsed = $this->trackFile['items'][$this->itemCurrentIndx];
                    // solo si el index del item encontrado es mayor a cero, lo colocamos al principio
                    if($this->itemCurrentIndx > 0) {
                        unset($this->trackFile['items'][$this->itemCurrentIndx]);
                        array_unshift($this->trackFile['items'], $this->itemCurrentResponsed);
                        $this->itemCurrentIndx = 0;
                    }
                    // Si no es atendido, lo marcamos como atendido para evitar enivar el mismo mensaje de Cot.
                    if(!$this->isAtendido) {
                        if(!in_array($this->itemCurrentResponsed['idItem'], $this->itemsToTrackeds)) {
                            $this->itemsToTrackeds[] = $this->itemCurrentResponsed['idItem'];
                            $this->updateTrackeds();
                        }
                    }
                    // mientras este en la lista del FileTrack es por que aun no ha terminado de cotizar
                    // por lo tanto forzamos a que isAtendida sea false para continuar con los
                    // siguientes pasos.
                    $this->isAtendido = false;
                }
            }
        }
    }

    /**
     * Eliminamos el item que se cotizó en Tracking
     */
    public function finDeCotizacion(array $cotProcess): bool
    {
        // Buscar en el estanque otra carnada
        // unset($this->trackFile['items'][$this->itemCurrentIndx]);
        // $this->updateTracking();
        return false;
    }

    /** 
    * Solo el no tengo, no tengo auto o fin de cotizacion, son los unicos eventos que disparan un
    * cierto proceso para ver si hay mas ordenes de solicitud de cotizaciones
    * para enviarle al cotizador.
    */
    public function fetchItemToSent(): array
    {
        $itemFetchToSent = [];
        // El archivo TrackFile existe, por lo tanto vemos si hay mas items
        if($this->hasItems) {

            // En caso de que este bacio el $this->itemCurrentResponsed, es que no se encontró
            // entre la lista de item respondido, pero aun asi, si hay mas items para enviar
            // los mandamos
            if($this->hasItems && count($this->itemCurrentResponsed) > 0) {
                if($this->trackFile['items'][0]['idItem'] == $this->itemCurrentResponsed['idItem']) {
                    unset($this->trackFile['items'][0]);
                    sort($this->trackFile['items']);
                }
            }

            // Si el cotizador nos esta diciendo que no tiene el auto
            // debemos eliminar todos los modelos coincidentes de la matriz.
            if($this->message->subEvento == 'ntga') {

                $copyFileTrack = [];
                $rota = count($this->trackFile['items']);
                for ($i=0; $i < $rota; $i++) {
                    if($this->trackFile['items'][$i]['mdl'] == $this->itemCurrentResponsed['mdl']) {
                        $this->itemsToTrackeds[] = $this->trackFile['items'][$i]['idItem'];
                    }else{
                        $copyFileTrack[] = $this->trackFile['items'][$i];
                    }
                }
                sort($copyFileTrack);
                $this->trackFile['items'] = $copyFileTrack;
            }

            // Despues de haber echo la limpieza del trackFile, revisamos si quedan items
            if(count($this->trackFile['items']) > 0) {
                $itemFetchToSent = $this->trackFile['items'][0];
            }
            $this->updateTracking();
        }

        return $itemFetchToSent;
    }

    /** */
    public function updateTracking()
    {
        $filename = $this->message->from.'.json';
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->fSys->setContent($filename, $this->trackFile);
    }

    /** */
    public function updateTrackeds()
    {
        if(count($this->itemsToTrackeds) > 0) {
            $filename = $this->message->from.'.json';
            $this->fSys->setPathBase($this->paths['trackeds']);
            $this->fSys->setContent($filename, $this->itemsToTrackeds);
        }
    }

}
