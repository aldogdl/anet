<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WapiResponse\FsysProcess;

class InteractiveProcess
{

    public String $hasErr = '';

    /** 
     * Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
     * que se necesita enviar como respuesta inmendiata a este
    */
    public function __construct(
        WaMsgMdl $message, array $paths, WebHook $wh, WrapHttp $wapiHttp,
    ) {

        $path = $paths['waTemplates'].$message->subEvento.'.json';
        $msg = json_decode(file_get_contents($path), true);
        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
        $conm->bodyRaw = $msg['body'];
        $conm->setBody('text', $msg);

        $result = $wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }
        
        $sended = $conm->setIdToMsgSended($message, $result);

        $fSys = new FsysProcess($paths['chat']);
        $fSys->dumpIn($message->toArray());
        $fSys->dumpIn($sended->toArray());

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'enviado'  => $sended->toArray(),
        ]);
    }

}
