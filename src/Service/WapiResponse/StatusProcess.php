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
        $fSys = new FsysProcess($pathChat);
        $chat = $fSys->get($message->toArray());
        if(count($chat) == 0) {
            return;
        }

        $stt = 'unknow';
        if(is_array($message->message)) {
            if(array_key_exists('stt', $message->message)) {
                $stt = $message->message['stt'];
            }
        }else{
            $stt = $message->message;
        }
        $chat['status'] = $stt;

        $fSys->dumpIn($chat);
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'procesado'=> $chat
        ]);
    }

}
