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
        $chat['status'] = $message->message;

        $fSys->dumpIn($chat);
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'procesado'=> $chat
        ]);
    }

}
