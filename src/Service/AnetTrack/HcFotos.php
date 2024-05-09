<?php

namespace App\Service\AnetTrack;

class HcFotos
{
    private HandlerQuote $handler;
    private String $txtValid = '';

    /** */
    public function __construct(HandlerQuote $handler)
    {
        $this->handler = $handler;
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(String $filename): bool {
        return $this->handler->fSys->existe('/', $filename);
    }

    /** */
    public function exe()
    {
        $filename = 'sfto.json';
        if(!$this->isAtendido($filename)) {
            $this->handler->fSys->setContent('/', $filename, ['']);
        }
        if($this->isAtendido('cnow')) {
            $this->handler->fSys->delete('/', $filename);
        }

        if(!$this->isValid()) {

        }
    }

    /** */
    private function isValid(): bool
    {
        return false;
    }

}
