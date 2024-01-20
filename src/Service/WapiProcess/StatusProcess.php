<?php

namespace App\Service\WapiProcess;

use App\Entity\EstanqueReturn;
use App\Entity\WaMsgMdl;
use App\Service\WebHook;

class StatusProcess
{
    public String $hasErr = '';

    /** */
    public function __construct(WaMsgMdl $message, array $paths, WebHook $wh)
    {
        $stt = 'unknow';
        if(is_array($message->message)) {
            if(array_key_exists('stt', $message->message)) {
                $stt = $message->message['stt'];
            }
        }else{
            $stt = $message->message;
        }

        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $fSys = new FsysProcess($paths['tracking']);
        $estanque = $fSys->getEstanqueOf($message->from);
        $result = new EstanqueReturn($estanque, $paths['hasCotPro']);

        $fSys->setPathBase($paths['chat']);
        $chat = $fSys->getChat($message->toArray());

        $hasChat = false;
        if(count($chat) == 0) {
            $chat['status'] = $stt;
            $fSys->dumpIn($chat);
            $hasChat = true;
        }

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'procesado'=> ($hasChat) ? $chat : 'Sin Chat',
            'estanque' => $result->toArray()
        ]);
    }

}
