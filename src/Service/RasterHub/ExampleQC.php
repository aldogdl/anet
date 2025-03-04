<?php

namespace App\Service\RasterHub;

class ExampleQC
{
    /** */
    public function build() : array {
        
        return [
            "type" => "image",
            "image" => [
                "link" => "https://autoparnet.com/wa_demo_cot/230_2.jpeg",
                "caption" => "#qc fascia trasera Volkswagen gol 2014 limpiar para pintar"
            ]
        ];
    }

}
