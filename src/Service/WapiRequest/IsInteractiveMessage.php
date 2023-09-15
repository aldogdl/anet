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
        // Buscamos el key interactive
        if( array_key_exists('type', $message) ) {
            
            $body = $message[$message['type']];
            
            // Buscamos el key interactive button_reply
            if( array_key_exists('type', $body) ) {
                
                $body = $body[$body['type']];

                if( array_key_exists('id', $body) ) {

                    if( mb_strpos($body['id'], 'ntg_') !== false) {
                        $this->isNtg = true;
                        return true;
                    }
    
                    if( mb_strpos($body['id'], 'cot_') !== false) {
                        $this->isCot = true;
                        return true;
                    }
                }
            }
        }

        $this->message = $message;
    }

}