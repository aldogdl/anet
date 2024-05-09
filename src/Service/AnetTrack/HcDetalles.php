<?php

namespace App\Service\AnetTrack;

class HcDetalles
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
        return $this->handler->fSys->existe('/', $this->handler->waMsg->from.'_'.$filename.'.json');
    }

    /** */
    public function exe()
    {
        $filename = 'sfto';
        if(!$this->isAtendido($filename)) {
            $this->handler->fSys->setContent('/', $filename, ['']);
        }
        if($this->isAtendido('cnow')) {
            $this->handler->fSys->delete('/', $filename);
        }
        $this->handler->waSender->setConmutador($this->handler->waMsg);

        if(!$this->isValid() && $this->txtValid != '') {
            $this->handler->waSender->sendText($this->txtValid);
            return;
        }

        $builder = new BuilderTemplates($this->handler->fSys, $this->handler->waMsg);
        $template = $builder->exe('sfto');
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = '';
        $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
        if(!in_array($this->handler->waMsg->status, $permitidas)) {
            $this->txtValid = "Lo sentimos pero el formato de imagen (".
            $this->handler->waMsg->status.") no está entre la lista de imágenes ".
            "permididas, por el momento sólo aceptamos fotos con extención:\n".
            "[".implode(', ', $permitidas)."].";
            return false;
        }
        if(count($this->handler->waMsg->content) == 0) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "recibida no es valida, por favor intenta enviarla nuevamente ".
            "o evnía otra como segunda opción.";
            return false;
        }

        if(!array_key_exists('id', $this->handler->waMsg->content)) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "no se envio correctamente a WHATSAPP, intenta enviarla ".
            "nuevamente por favor.";
            return false;
        }

        return true;
    }

}
