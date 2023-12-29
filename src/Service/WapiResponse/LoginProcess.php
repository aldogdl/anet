<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WapiResponse\FsysProcess;

class LoginProcess
{

    public String $hasErr = '';

    /** */
    public function __construct(
        WaMsgMdl $message, String $conmutaPath, String $pathChat, WebHook $wh, WrapHttp $wapiHttp,
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

        $conm = new ConmutadorWa($message->from, $conmutaPath);
        $conm->bodyRaw = "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando;
        $conm->setBody('text', ["preview_url" => false, "body" => $conm->bodyRaw]);

        $result = $wapiHttp->send($conm);
        $sended = $conm->setIdToMsgSended($message, $result);

        $fSys = new FsysProcess($pathChat);
        $fSys->dumpIn($message->toArray());
        $fSys->dumpIn($sended->toArray());

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended->toArray(),
        ]);
    }

}
