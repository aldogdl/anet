<?php

namespace App\Service\WapiProcess;

use App\Entity\EstanqueReturn;
use App\Entity\WaMsgMdl;
use App\Service\WapiProcess\FsysProcess;

class TrackFileCot {

    public FsysProcess $fSys;
    private array $paths;
    
    public WaMsgMdl $message;
    /** */
    public array $trackFile = [];
    /** El item que se esta cotizando actualmente */
    public array $baitProgress = [];
    /** Indica si hay mas carnada en el Estanque */
    public bool $hasBaits = false;
    /** El indice del item que disparo el evento */
    public int $indexItemTrigger = -1;
    // Revisamos que el item que se respondio no exista entre los atendidos
    public bool $isAtendido = false;
    // El item el cual fue respondido por medio de los botones
    public int $versionFileTrack = -1;

    /** 
     * Tomamos el trackFile del cotizador.
     * Buscamos el item respondido y lo pasamos a itemsToTrackeds.
     * A su ves lo eliminamos de la lista de trackFile
    */
    public function __construct(WaMsgMdl $message, array $paths, FsysProcess $fSys = null)
    {
        $this->paths      = $paths;
        $this->message    = $message;
        $this->isAtendido = false;
        if($fSys == null) {
            $this->fSys = new FsysProcess($paths['trackeds']);
            $this->getFileContentTrackeds();
        }else{
            $this->fSys = $fSys;
            $this->fSys->setPathBase($paths['trackeds']);
        }
    }

    /**
     * Extraemos el archivo de rastreados del cotizador
     */
    public function getFileContentTrackeds(): array 
    {
        $trakeds = [];
        if(!is_array($this->message->message)) {
            return [];
        }

        $this->fSys->getContent($this->message->from.'.json');
        if(array_key_exists('idItem', $this->message->message)) {
            if(in_array($this->message->message['idItem'], $trakeds)) {
                $this->isAtendido = true;
            }
        }
        return $trakeds;
    }
    
    /** */
    public function build()
    {
        // Tomamos el archivo TrackFile
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->trackFile = $this->fSys->getEstanqueOf($this->message->from);
        
        if(count($this->trackFile) > 0) {
            $this->versionFileTrack = $this->trackFile['version'];
            $this->fetchBaitProgress();
        }
    }

    /** */
    public function finOfCotizacion(): void
    {
        $this->build();
        // Eliminamos el archivo que indica que se esta cotizando
        $this->deleteFileCotProcess();

        if(count($this->baitProgress) > 0) {

            // Se encontró el bait dentro del estanque
            $trackeds = $this->getFileContentTrackeds();
            if(!in_array($this->baitProgress['idItem'], $trackeds)) {
                $trackeds[] = $this->baitProgress['idItem'];
            }
            $this->updateTrackeds($trackeds, false);

            // El encontrado en el estanque lo borramos
            unset($this->trackFile['items'][$this->indexItemTrigger]);
            sort($this->trackFile['items']);
            $this->updateTracking();

            $this->baitProgress = [];
            $bait = $this->lookForBait(true);
            if(count($bait) > 0) {
                $this->baitProgress = $bait;
                $bait = [];
            }
        }
    }

    /** 
    * Solo el no tengo, no tengo auto o fin de cotizacion, son los unicos eventos que
    * nos hace buscar una nueva carnada
    */
    public function lookForBait(bool $isBuilded = false): array
    {
        if(!$isBuilded) {
            $this->build();
        }

        $itemFetchToSent = [];
        if($this->hasBaits) {
            
            $trackeds = [];
            // En caso de que el Item se halla encontrado aun dentro de FileTrack lo enviamos
            // a trackeds y lo eliminamos del FileTrack
            $cotProcessIsFill = false;
            // Si hay cambios en el TrackFile lo guardamos al final de este metodo
            $hasChangeFileTrack = false;
            if(count($this->baitProgress) > 0) {
                if($this->trackFile['items'][$this->indexItemTrigger]['idItem'] == $this->baitProgress['idItem']) {
                    $trackeds[] = $this->baitProgress['idItem'];
                    unset($this->trackFile['items'][$this->indexItemTrigger]);
                    sort($this->trackFile['items']);
                    $hasChangeFileTrack = true;
                }
                $cotProcessIsFill = true;
            }

            // Si el cotizador nos esta diciendo que no tiene el auto
            // debemos eliminar todos los modelos coincidentes de la matriz.
            if($this->message->subEvento == 'ntga' && $cotProcessIsFill) {

                $copyFileTrack = [];
                $rota = count($this->trackFile['items']);
                for ($i=0; $i < $rota; $i++) {
                    if($this->trackFile['items'][$i]['mdl'] == $this->baitProgress['mdl']) {
                        $trackeds[] = $this->trackFile['items'][$i]['idItem'];
                    }else{
                        $copyFileTrack[] = $this->trackFile['items'][$i];
                    }
                }
                sort($copyFileTrack);
                $this->trackFile['items'] = $copyFileTrack;
                $hasChangeFileTrack = true;
            }

            // Despues de haber echo la limpieza del trackFile, revisamos si quedan items
            if(count($this->trackFile['items']) > 0) {
                $itemFetchToSent = $this->trackFile['items'][0];
            }

            if($hasChangeFileTrack) {
                $this->updateTracking();
            }

            if(count($trackeds) > 0) {
                $this->updateTrackeds($trackeds);
            }
        }

        return $itemFetchToSent;
    }

    /** */
    public function fetchBaitProgress() {

        $this->baitProgress = [];
        $this->indexItemTrigger = false;
        $this->hasBaits = false;
        if(!array_key_exists('items', $this->trackFile)) {
            // No hay mas items
            return;
        }
        if(count($this->trackFile['items']) == 0) {
            // No hay mas items
            return;
        }

        $this->hasBaits = true;
        // 1.- Tomamos el item que disparo este evento (el respondido por un boton)
        $idsItems = array_column($this->trackFile['items'], 'idItem');
        $this->indexItemTrigger = array_search($this->message->message['idItem'], $idsItems);
        
        if($this->indexItemTrigger !== false) {
            $this->baitProgress = $this->trackFile['items'][$this->indexItemTrigger];
            // solo si el index del item encontrado es mayor a cero, lo colocamos al principio
            if($this->indexItemTrigger > 0) {
                unset($this->trackFile['items'][$this->indexItemTrigger]);
                array_unshift($this->trackFile['items'], $this->baitProgress);
                $this->indexItemTrigger = 0;
                $this->updateTracking();
            }
        }
    }

    /** */
    public function getEstanqueReturn(array $baitForce = [], String $type = 'less'): array
    {
        $hasCot = (count($this->baitProgress) > 0) ? true : false;
        $est = new EstanqueReturn($this->trackFile, $type, $hasCot, $baitForce);
        return $est->toArray();
        return [];
    }

    /** */
    public function updateTrackeds(array $news, bool $fetchInFile = true)
    {
        $this->fSys->setPathBase($this->paths['trackeds']);
        if($fetchInFile) {
            $trackeds = $this->fSys->getTrackedsFileOf($this->message->from);
            $trackeds = array_merge($trackeds, $news);
        }else{
            $trackeds = $news;
        }
        $this->fSys->setContent($this->message->from.'.json', $trackeds);
    }

    /** 
     * El archivo que indica que el cotizador esta en proceso de cotizacion
    */
    public function deleteFileCotProcess()
    {
        $this->fSys->setPathBase($this->paths['cotProgres']);
        $this->fSys->delete($this->message->from.'.json');
    }

    /** */
    public function updateTracking()
    {
        $this->fSys->setPathBase($this->paths['tracking']);
        $this->fSys->setContent($this->message->from.'.json', $this->trackFile);
    }

}
