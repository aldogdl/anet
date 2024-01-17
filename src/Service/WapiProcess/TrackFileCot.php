<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WapiProcess\FsysProcess;

class TrackFileCot {

    public FsysProcess $fSys;
    private WaMsgMdl $message;
    private array $paths;
    
    /** */
    public array $trackFile = [];
    /** El item que se esta cotizando actualmente */
    public array $cotProcess = [];
    /** Indica si hay mas carnada en el Estanque */
    public bool $hasBaits = false;
    /** El indice del item que disparo el evento */
    public int $indexItemTrigger = -1;
    // Revisamos que el item que se respondio no exista entre los atendidos
    public bool $isAtendido = false;
    // El item el cual fue respondido por medio de los botones
    public $itemCurrentResponsed = [];

    /** 
     * Tomamos el trackFile del cotizador.
     * Buscamos el item respondido y lo pasamos a itemsToTrackeds.
     * A su ves lo eliminamos de la lista de trackFile
    */
    public function __construct(WaMsgMdl $message, array $paths)
    {
        $this->paths      = $paths;
        $this->message    = $message;
        $this->isAtendido = false;
        $this->fSys       = new FsysProcess($paths['trackeds']);
        $trakeds          = $this->fSys->getContent($this->message->from.'.json');

        if(in_array($this->message['idItem'], $trakeds)) {
            $this->isAtendido = true;
        }
    }

    /**
     * Eliminamos el item que se cotizó en Tracking
     */
    public function finDeCotizacion(array $cotProcessCurrent): bool
    {
        // Buscar en el estanque otra carnada
        // unset($this->trackFile['items'][$this->itemCurrentIndx]);
        // $this->updateTracking();

        // Si no es atendido, lo marcamos como atendido para evitar enivar el mismo mensaje de Cot.
        // if(!$this->isAtendido) {
        //     if(!in_array($this->itemCurrentResponsed['idItem'], $this->itemsToTrackeds)) {
        //         $this->itemsToTrackeds[] = $this->itemCurrentResponsed['idItem'];
        //         $this->updateTrackeds();
        //     }
        // }
        // // mientras este en la lista del FileTrack es por que aun no ha terminado de cotizar
        // // por lo tanto forzamos a que isAtendida sea false para continuar con los
        // // siguientes pasos.
        // $this->isAtendido = false;
        return false;
    }

    /** 
    * Solo el no tengo, no tengo auto o fin de cotizacion, son los unicos eventos que
    * nos hace buscar una nueva carnada
    */
    public function lookForBait(): array
    {
        $this->build();
        $trackeds = [];
        if($this->hasBaits) {

            // En caso de que el Item se halla encontrado aun dentro de FileTrack lo enviamos
            // a trackeds y lo eliminamos del FileTrack
            $cotProcessIsFill = false;
            if(count($this->cotProcess) > 0) {
                if($this->trackFile['items'][$this->indexItemTrigger]['idItem'] == $this->cotProcess['idItem']) {
                    $trackeds[] = $this->cotProcess;
                    unset($this->trackFile['items'][0]);
                    sort($this->trackFile['items']);
                }
                $cotProcessIsFill = true;
            }

            // Si el cotizador nos esta diciendo que no tiene el auto
            // debemos eliminar todos los modelos coincidentes de la matriz.
            if($this->message->subEvento == 'ntga' && $cotProcessIsFill) {

                $copyFileTrack = [];
                $rota = count($this->trackFile['items']);
                for ($i=0; $i < $rota; $i++) {
                    if($$this->trackFile['items'][$i]['mdl'] == $this->cotProcess['mdl']) {
                        $trackeds[] = $this->trackFile['items'][$i]['idItem'];
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

            if(count($trackeds) > 0) {
                $this->updateTrackeds($trackeds);
            }
        }

        return $itemFetchToSent;
    }
    
    /** */
    public function build()
    {
        // Tomamos el archivo TrackFile
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->trackFile = $this->fSys->getTrackFileOf($this->message->from);
        
        if(count($this->trackFile) > 0) {
            
            if(count($this->trackFile['items']) > 0) {
                
                $this->hasBaits = true;
                // 1.- Tomamos el item que disparo este evento (el respondido por un boton)
                $idsItems = array_column($this->trackFile['items'], 'idItem');
                $this->indexItemTrigger = array_search($this->message->message['idItem'], $idsItems);
                
                if($this->indexItemTrigger !== false) {
                    $this->cotProcess = $this->trackFile['items'][$this->indexItemTrigger];
                    // solo si el index del item encontrado es mayor a cero, lo colocamos al principio
                    if($this->indexItemTrigger > 0) {
                        unset($this->trackFile['items'][$this->indexItemTrigger]);
                        array_unshift($this->trackFile['items'], $this->cotProcess);
                        $this->indexItemTrigger = 0;
                        $this->updateTracking();
                    }
                }
            }
        }
    }

    /** */
    public function updateTrackeds(array $news)
    {
        $this->fSys->setPathBase($this->paths['trackeds']);
        $trackeds = $this->fSys->getTrackedsFileOf($this->message->from);
        $trackeds = array_merge($trackeds, $news);
        $this->fSys->setContent($this->message->from.'.json', $trackeds);
    }

    /** */
    public function updateTracking()
    {
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->fSys->setContent($this->message->from.'.json', $this->trackFile);
    }

}
