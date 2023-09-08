<?php

namespace App\Service\WapiRequest;

class IsCotizacionMessage {

    public bool $inTransit = false;

    private array $message;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(array $message)
    {
        $this->message = $message;
    }

}