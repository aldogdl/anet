<?php

namespace App\Service\WapiProcess;

use App\Entity\EstanqueReturn;
use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;
use App\Service\WapiProcess\FsysProcess;

class LoginProcess
{

    public String $hasErr = '';

    private WaMsgMdl $message;
    private array $paths;
    private WebHook $wh;
    private WrapHttp $wapiHttp;

    /** */
    public function __construct(
        WaMsgMdl $message, array $paths, WebHook $wh, WrapHttp $wapiHttp,
    ) {
        
        $this->message = $message;
        $this->message->subEvento = 'iniLogin';
        $this->paths = $paths;
        $this->wh = $wh;
        $this->wapiHttp = $wapiHttp;
        
    }

    /** */
    public function isAtendido(): bool {

        try {
            if(file_exists($this->message->from.'_'.$this->message->subEvento.'.txt') !== false) {
                return true;
            }
        } catch (\Throwable $th) {}
        return false;
    }

    /** */
    public function exe() {

        $cuando = '';
        try {
            $date = new \DateTime(strtotime($this->message->creado));
            $timeFin = $date->format('h:i:s a');
        } catch (\Throwable $th) {
            $this->hasErr = $th->getMessage();
        }
        
        if($this->hasErr == '') {
            $cuando = " a las " . $timeFin;
        }

        $conm = new ConmutadorWa($this->message->from, $this->paths['tkwaconm']);
        $conm->bodyRaw = "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando;
        $conm->setBody('text', ['text' => ["preview_url" => false, "body" => $conm->bodyRaw]]);

        $result = $this->wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $this->wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }

        $sended = $conm->setIdToMsgSended($this->message, $result);

        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $fSys = new FsysProcess($this->paths['tracking']);
        $estanque = $fSys->getEstanqueOf($this->message->from);
        $result = new EstanqueReturn($estanque, [], $this->paths['hasCotPro']);

        // Guardamo un archivo temporal para evitar enviar multiples mensajes de inicio de Sesion
        // cuando el envio a Ngrok se alenta demaciado.
        $fileTmp = $this->message->from.'_'.$this->message->subEvento.'.txt';
        file_put_contents($fileTmp, '');

        $res = $this->wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $this->message->toArray(),
            'enviado'  => $sended->toArray(),
            'estanque' => $result->toArray()
        ]);
        if($res) {
            if(file_exists($fileTmp) !== false) {
                unlink($fileTmp);
            }
        }
    }
}
