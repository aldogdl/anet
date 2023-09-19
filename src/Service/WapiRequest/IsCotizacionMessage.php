<?php

namespace App\Service\WapiRequest;

class IsCotizacionMessage {

    private array $message;
    private String $pathCotz;
    
    public bool $inTransit = false;
    public String $pathFull;
    public array $sortCot = ['fotos', 'detalles', 'costo'];

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(array $message, String $pathCotz)
    {
        $this->message  = $message;
        $this->pathCotz = $pathCotz;
        $this->pathFull = $this->pathCotz.$this->message['from'].'.json';
        $this->hasCotizacionInTransit();
    }

    /**
     * Revisamos si existe un archivo de contizacion en transito
     */
    private function hasCotizacionInTransit() {

        if(is_file($this->pathFull)) {
            $this->inTransit = true;
        }
    }

    /**
     * Creamos un archivo de contizacion en transito
     */
    public function setStepCotizacionInTransit(int $step = 0) {

        // Guardamos la referencia al mensaje de cotizacion respondido
        // por medio de boton cotizar, solo esa ocación
        $wamid = '';
        if(array_key_exists('context', $this->message)) {
            $wamid = $this->message['context']['id'];
        }

        file_put_contents($this->pathFull, json_encode([
            'wamid'   => $wamid,
            'current' => $this->sortCot[$step],
            'values'  => [ $this->sortCot[$step] => [] ]
        ]));
    }

    /**
     * Actualizamos el archivo de contizacion en transito
     */
    public function updateStepCotizacionInTransit(int $step, array $fileCot): array {

        $fileCot['current'] = $this->sortCot[$step];
        $fileCot['values'][$this->sortCot[$step]] = [];
        file_put_contents($this->pathFull, json_encode($fileCot));
        return $fileCot;
    }

    /**
     * Eliminamos el archivo de contizacion en transito
     */
    public function finishCotizacionInTransit() {

        unlink($this->pathFull);
    }

    /**
     * Recuperamos el archivo de contizacion en transito
     */
    public function getCotizacionInTransit(): array {

        return json_decode(file_get_contents($this->pathFull), true);
    }

    /// Cuando hay una cotizacion en curso y el cotizador presiona el botón de cotizar
    /// o de no tengo de otra solicitud 
    public function getMsgErrorOtraCot(array $cotInTransit): array {

        $espera = '';
        switch ($cotInTransit['current']) {
            case 'detalles':
                $espera = '📝 Se esperaban los *Detalles';
                break;
            case 'costo':
                $espera = '💰 Se esperaba el *Costo';
                break;
            default:
                $espera = '📷 Se esperaban *Fotografías';
                break;
        }

        return [
            "context"     => $cotInTransit["wamid"],
            "preview_url" => false,
            "body"        => $espera."* de la Pieza.\n\n🚗 Cotización en Curso..."
        ];
    }

}