<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WapiResponse\FsysProcess;

class TrackFileCot {

    private FsysProcess $fSys;
    private WaMsgMdl $message;
    private array $paths;
    
    public bool $hasItems = false;
    // Revisamos que el item que se respondio no exista entre los atendidos
    public bool $isAtendido = false;
    // El archivo completo del fileTrack del cotizador, se manipulara y se actualizara
    public array $fileTrackItem = [];
    // El item el cual fue respondido por medio de los botones
    private $itemCurrentResponsed = [];
    // Los items que se enviarán al archivo trackeds de atendidos.
    private $itemsToTrackeds = [];

    /** 
     * Tomamos el FileTrack del cotizador.
     * Buscamos el item respondido y lo pasamos a itemsToTrackeds.
     * A su ves lo eliminamos de la lista de fileTrack
    */
    public function __construct(WaMsgMdl $message, array $paths) {

        $this->paths = $paths;
        $this->message = $message;
        
        $this->fSys = new FsysProcess($this->paths['trackeds']);
        $this->itemsToTrackeds = $this->fSys->getTrackedsFileOf($this->message->from);
        $itemCurrentIndx = array_search($this->message->message['idItem'], $this->itemsToTrackeds);
        
        // Siempre que el cotizador responda a cualquier boton, el sistema registrará este item
        // como atendido, pero... si se encuentra entre la lista de trakings es por que esta en
        // un proceso de cotización.
        if($itemCurrentIndx !== false) {
            $this->isAtendido = true;
        }
        
        // Tomamos el archivo TrackFile
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->fileTrackItem = $this->fSys->getTrackFileOf($this->message->from);

        if(count($this->fileTrackItem) > 0) {
            
            if(count($this->fileTrackItem['items']) > 0) {
                
                $this->hasItems = true;
                // 1.- Tomamos el item que disparo este evento (el respondido por un boton)
                $idsItems = array_column($this->fileTrackItem['items'], 'idItem');
                $itemCurrentIndx = array_search($this->message->message['idItem'], $idsItems);
                
                if($itemCurrentIndx !== false) {
                    $this->itemCurrentResponsed = $this->fileTrackItem['items'][$itemCurrentIndx];
                    // solo si el index del item encontrado es mayor a cero, lo colocamos al principio
                    if($itemCurrentIndx > 0) {
                        unset($this->fileTrackItem['items'][$itemCurrentIndx]);
                        array_unshift($this->fileTrackItem['items'], $this->itemCurrentResponsed);
                    }
                    // Si no es atendido, lo marcamos como atendido para evitar enivar el mismo mensaje de Cot.
                    if(!$this->isAtendido) {
                        $this->itemsToTrackeds[] = $this->itemCurrentResponsed['idItem'];
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
    * Solo el no tengo o no tengo auto, son los unicos eventos que disparan un
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
                if($this->fileTrackItem['items'][0]['idItem'] == $this->itemCurrentResponsed['idItem']) {
                    unset($this->fileTrackItem['items'][0]);
                    sort($this->fileTrackItem['items']);
                }
            }

            // Si el cotizador nos esta diciendo que no tiene el auto
            // debemos eliminar todos los modelos coincidentes de la matriz.
            if($this->message->subEvento == 'ntga') {

                $copyFileTrack = [];
                $rota = count($this->fileTrackItem['items']);
                for ($i=0; $i < $rota; $i++) {
                    if($this->fileTrackItem['items'][$i]['mdl'] == $this->itemCurrentResponsed['mdl']) {
                        $this->itemsToTrackeds[] = $this->fileTrackItem['items'][$i]['idItem'];
                    }else{
                        $copyFileTrack[] = $this->fileTrackItem['items'][$i];
                    }
                }
                sort($copyFileTrack);
                $this->fileTrackItem['items'] = $copyFileTrack;
            }

            // Despues de haber echo la limpieza del trackFile, revisamos si quedan items
            if(count($this->fileTrackItem['items']) > 0) {
                $itemFetchToSent = $this->fileTrackItem['items'][0];
            }
            $this->update();
        }

        return $itemFetchToSent;
    }

    /** */
    public function update()
    {
        if(!$this->hasItems){ return; }

        $filename = $this->message->from.'.json';
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->fSys->setContent($filename, $this->fileTrackItem);
        if(count($this->itemsToTrackeds) > 0) {
            $this->fSys->setPathBase($this->paths['trackeds']);
            $this->fSys->setContent($filename, $this->itemsToTrackeds);
        }
    }

}
