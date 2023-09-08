<?php

namespace App\Service\WapiRequest;

class IsLoginMessage {

    public bool $isLogin;

    private array $token = [
        'Hola', 'AutoparNet', 'atenderte', 'piezas', 'necesitas'
    ];

    /** */
    public function __construct(array $message)
    {
        $body = '';
        if( array_key_exists('type', $message) ) {
            $body = $message[$message['type']];
            if( array_key_exists('body', $body) ) {
                $body = $body['body'];
            }
        }

        $palClas = [];
        $partes = explode(' ', $body);
        
        $rota = count($partes);
        for ($i=0; $i < $rota; $i++) { 
            if(in_array($partes, $this->token)) {
                $palClas[] = $partes[$i];
            }
        }

        if(count($partes) == count($palClas)) {
            $this->isLogin = true;
        }
    }
}