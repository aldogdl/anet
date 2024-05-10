<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;

class HcFotos
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;
    private String $txtValid = '';

    /** */
    public function __construct(Fsys $fsys, WaSender $waS, WaMsgDto $msg, array $bait)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->bait = $bait;
    }

    /** */
    private function createFilenameTmpOf(String $name): String {
        return $this->waMsg->from.'_'.$name.'.json';
    }

    /** 
     * Cuando NiFi o Ngrok no responden inmediatamente por causa de latencia, whatsapp
     * considera que no llego el mensaje a este servidor, por lo tanto reenvia el mensaje
     * causando que el usuario reciba varios mensajes de confirmación.
     * -- Con la estrategia de crear un archivo como recibido el msg de inicio de sesion
     * evitamos esto.
    */
    public function isAtendido(String $filename): bool {
        return $this->fSys->existe('/', $filename);
    }

    /** */
    public function exe(): array
    {
        // Creamos el archivo indicativo del proceso actual
        $filename = $this->createFilenameTmpOf('sfto');
        if(!$this->isAtendido($filename)) {
            $this->fSys->setContent('/', $filename, ['']);
        }
        // Eliminamos el archivo indicativo del proceso anterior
        $filename = $this->createFilenameTmpOf('cnow');
        if($this->isAtendido($filename)) {
            $this->fSys->delete('/', $filename);
        }
        $this->waSender->setConmutador($this->waMsg);

        // Validamos la integridad del tipo de mensaje
        if(!$this->isValid() && $this->txtValid != '') {
            $this->waSender->sendText($this->txtValid);
            return [];
        }
        
        $track = [];
        if(array_key_exists('track', $this->bait)) {
            $track = $this->bait['track'];
        }
        if(!array_key_exists('fotos', $track)) {
            $track['fotos'] = [$this->waMsg->content];
        }else{
            array_push($track['fotos'], [$this->waMsg->content]);
        }

        $this->bait['track'] = $track;
        $this->bait['current'] = 'sdta';
        $this->fSys->setContent(
            'tracking', $this->waMsg->from.'.json', $this->bait
        );

        $builder = new BuilderTemplates($this->fSys, $this->waMsg);
        $template = $builder->exe('sdta');
        if(count($template) > 0) {
            $res = $this->waSender->sendInteractive($template);
            if($res >= 200 && $res <= 300) {
                $this->waSender->sendMy($this->waMsg->toMini());
            }
        }else{
            $this->waSender->sendText(
                "*Muy bien gracias*.\n\n📝Ahora puedes describir un poco la ".
                "CONDICIÓN O ESTADO de tu autoparte por favor."
            );
        }

        return $this->bait;
    }

    /** */
    private function isValid(): bool
    {
        $this->txtValid = '';
        $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
        if(!in_array($this->waMsg->status, $permitidas)) {
            $this->txtValid = "Lo sentimos pero el formato de imagen (".
            $this->waMsg->status.") no está entre la lista de imágenes ".
            "permididas, por el momento sólo aceptamos fotos con extención:\n".
            "[".implode(', ', $permitidas)."].";
            return false;
        }
        if(count($this->waMsg->content) == 0) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "recibida no es valida, por favor intenta enviarla nuevamente ".
            "o evnía otra como segunda opción.";
            return false;
        }

        if(!array_key_exists('id', $this->waMsg->content)) {
            $this->txtValid = "Lo sentimos pero la imagen (".
            "no se envio correctamente a WHATSAPP, intenta enviarla ".
            "nuevamente por favor.";
            return false;
        }

        return true;
    }

}
