<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;
use App\Service\WapiProcess\FsysProcess;

class LoginProcess
{

    public String $hasErr = '';

    /** */
    public function __construct(
        WaMsgMdl $message, String $conmutaPath, String $pathChat, WebHook $wh, WrapHttp $wapiHttp,
    ) {
        file_put_contents('wa_initLogin.txt', '');
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
        $conm->setBody('text', ['text' => ["preview_url" => false, "body" => $conm->bodyRaw]]);

        $result = $wapiHttp->send($conm);
        file_put_contents('wa_initLogin_res.txt', json_encode($result));
        if($result['statuscode'] != 200) {
            $wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }
        
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
