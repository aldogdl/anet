<?php

namespace App\Entity;

class EstanqueReturn {

    private bool $hasCotProgress = false;
    private int $version = 0;
    private int $cantBait = 0;
    private String $typeBait = 'empty';
    private array $bait = [];

    /** 
     * Tipos de Bait
     * empty: No existe carnada ni cotizacion en progreso
     * first: Cuando se toma el primero de la lista del estanque
     * less : Cuando se envia el que se esta atendiendo en el momento
     * bait : Cuando es un nuevo item que se envio al cotizador
    */
    public function __construct(
        array $est, String $type = 'empty', bool $hasCotPro = false, array $baitForce = []
    ) {

        $this->hasCotProgress = $hasCotPro;
        $this->typeBait = $type;
        if(count($est) > 0) {
            $this->cantBait = count($est['items']);
            $this->version = $est['version'];
        }
        if(count($baitForce) > 0) {
            $this->bait = $baitForce;
        }else{
            $this->bait = ($this->cantBait > 0) ? $est['items'][0] : [];
            $this->typeBait = ($this->cantBait > 0) ? 'first' : 'empty';
        }
    }

    /** */
    public function toArray() {

        return [
            'version' => $this->version,
            'hasCotProgress' => $this->hasCotProgress,
            'cantBait' => $this->cantBait,
            'typeBait' => $this->typeBait,
            'bait' => $this->bait,
        ];
    }
}