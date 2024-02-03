<?php

namespace App\Entity;

class EstanqueReturn {

    private int $version     = 0;
    private int $cantBait    = 0;
    private array $bait      = [];
    private array $baitNext  = [];
    private String $typeBait = 'empty';
    private bool $hasCotProgress = false;

    /** 
     * Tipos de Bait
     * empty: No existe carnada ni cotizacion en progreso
     * first: Cuando se toma el primero de la lista del estanque
     * less : Cuando se envia el que se esta atendiendo en el momento
     * bait : Cuando es un nuevo item que se envio al cotizador
    */
    public function __construct(
        array $est, array $baitProgress, bool $hasCotPro = false, String $type = 'empty',
    ) {

        $this->bait = [];
        $this->baitNext = [];
        $this->hasCotProgress = $hasCotPro;
        $this->typeBait = $type;

        if(count($est) > 0) {
            $this->cantBait = count($est['items']);
            $this->version = $est['version'];
            if($this->cantBait >= 1) {
                $this->baitNext = $est['items'][1];
            }
        }
        if(count($baitProgress) > 0) {
            $this->bait = $baitProgress;
        }
    }

    /** */
    public function toArray() {

        return [
            'baitProgress' => $this->bait,
            'estData' => [
                'typeBait' => $this->typeBait,
                'version'  => $this->version,
                'cantBait' => $this->cantBait,
                'baitNext' => $this->baitNext,
                'hasCotProgress' => $this->hasCotProgress,
            ],
        ];
    }
}