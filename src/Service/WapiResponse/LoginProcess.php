<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiResponse\WrapHttp;

class LoginProcess
{
    public String $hasErr = '';

    /** */
    public function __construct(WaMsgMdl $message, String $conmutaPath, WebHook $wh, WrapHttp $wapiHttp)
    {
        $cuando = '';
        $timeFin = $this->getTimeKdk($message->creado);
        if($this->hasErr == '') {
            $cuando = " a las " . $timeFin;
        }

        $conm = new ConmutadorWa($message->from, $conmutaPath);
        $conm->bodyRaw = "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando;
        $conm->setBody(
            'text',
            [
                "preview_url" => false,
                "body" => $conm->bodyRaw
            ]
        );
        $message->subEvento = 'iniLogin';

        $result = $wapiHttp->send($conm);
        $sended = $conm->setIdToMsgSended($message, $result);
        
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended->toArray(),
        ]);
    }

    ///
    private function getTimeKdk(String $timestamp): String {

        try {
            $date = new \DateTime(strtotime($timestamp));
            return $date->format('h:i:s a');
        } catch (\Throwable $th) {
            $this->hasErr = $th->getMessage();
        }
    }

}