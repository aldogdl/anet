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

    /** */
    public function __construct(
        WaMsgMdl $message, array $paths, WebHook $wh, WrapHttp $wapiHttp,
    ) {
        
        $cuando = '';
        $message->subEvento = 'iniLogin';
        try {
            $date = new \DateTime(strtotime($message->creado));
            $timeFin = $date->format('h:i:s a');
        } catch (\Throwable $th) {
            $this->hasErr = $th->getMessage();
        }
        
        if($this->hasErr == '') {
            $cuando = " a las " . $timeFin;
        }

        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
        $conm->bodyRaw = "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando;
        $conm->setBody('text', ['text' => ["preview_url" => false, "body" => $conm->bodyRaw]]);

        $result = $wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }

        $sended = $conm->setIdToMsgSended($message, $result);

        $fSys = new FsysProcess($paths['chat']);
        $fSys->dumpIn($message->toArray());
        $fSys->dumpIn($sended->toArray());

        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $fSys->setPathBase($paths['tracking']);
        $estanque = $fSys->getEstanqueOf($message->from);
        $result = new EstanqueReturn($estanque, $paths['hasCotPro']);

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended->toArray(),
            'estanque' => $result->toArray()
        ]);
    }

}
