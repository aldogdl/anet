<?php

namespace App\Entity;

class EstanqueReturn {

    private bool $hasCotProgress = false;
    private int $version = 0;
    private int $cantBait = 0;
    private array $bait = [];

    /** */
    public function __construct(array $est, bool $hasCotPro = false) {

        if(count($est) > 0) {
            $this->cantBait = count($est['items']);
            $this->hasCotProgress = $hasCotPro;
            $this->version = $est['version'];
            $this->bait = ($this->cantBait > 0) ? $est['items'][0] : [];
        }
    }

    /** */
    public function toArray() {

        return [
            'version' => $this->version,
            'hasCotProgress' => $this->hasCotProgress,
            'cantBait' => $this->cantBait,
            'bait' => $this->bait,
        ];
    }
}