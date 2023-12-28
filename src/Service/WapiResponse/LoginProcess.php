<?php

namespace App\Service\WapiResponse;

use App\Service\WebHook;
use App\Service\WapiResponse\WrapHttp;

class LoginProcess
{
    public String $hasErr = '';

    /** */
    public function __construct(array $message, String $conmutaPath, WebHook $wh, WrapHttp $wapiHttp)
    {
        $cuando = '';
        $timeFin = $this->getTimeKdk($message['creado']);
        if($this->hasErr == '') {
            $cuando = " a las " . $timeFin;
        }

        $conm = new ConmutadorWa($message['from'], $conmutaPath);
        $conm->setBody(
            'text',
            [
                "preview_url" => false,
                "body" => "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando
            ]
        );
        $result = $wapiHttp->send($conm);

        $msg['subEvento'] = 'iniLogin';
        $msg['response']  = $result;
        $wh->sendMy('wa-wh', 'notSave', $msg);
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