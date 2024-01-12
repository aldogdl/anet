<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;

class StatusProcess
{
    public String $hasErr = '';

    /** */
    public function __construct(WaMsgMdl $message, String $pathChat, WebHook $wh)
    {
        $stt = 'unknow';
        $fSys = new FsysProcess($pathChat);
        $chat = $fSys->getChat($message->toArray());

        if(is_array($message->message)) {
            if(array_key_exists('stt', $message->message)) {
                $stt = $message->message['stt'];
            }
        }else{
            $stt = $message->message;
        }

        $hasChat = false;
        if(count($chat) == 0) {
            $chat['status'] = $stt;
            $fSys->dumpIn($chat);
            $hasChat = true;
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'procesado'=> ($hasChat) ? $chat : 'Sin Chat'
        ]);
    }

}
