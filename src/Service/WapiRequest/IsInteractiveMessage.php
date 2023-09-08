<?php

namespace App\Service\WapiRequest;

class IsInteractiveMessage {

    public bool $isNtg = false;
    public bool $isCot = false;

    private array $message;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(array $message)
    {
        $this->message = $message;
    }

}