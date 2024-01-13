<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;

class StatusProcess
{
    public String $hasErr = '';

    /** */
    public function __construct(WaMsgMdl $message, String $pathChat, String $pathTrackFile, WebHook $wh)
    {
        $stt = 'unknow';
        if(is_array($message->message)) {
            if(array_key_exists('stt', $message->message)) {
                $stt = $message->message['stt'];
            }
        }else{
            $stt = $message->message;
        }

        $fSys = new FsysProcess($pathTrackFile);
        $trackFile = $fSys->getTrackFileOf($message->from);

        $fSys->setPathBase($pathChat);
        $chat = $fSys->getChat($message->toArray());

        $hasChat = false;
        if(count($chat) == 0) {
            $chat['status'] = $stt;
            $fSys->dumpIn($chat);
            $hasChat = true;
        }
        $version = 0;
        if(count($trackFile) > 0 && array_key_exists('version', $trackFile)) {
            $version = $trackFile['version'];
        }
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'procesado'=> ($hasChat) ? $chat : 'Sin Chat',
            'trackfile'=> $version
        ]);
    }

}
