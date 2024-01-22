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
        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $fSys = new FsysProcess($paths['tracking']);
        $estanque = $fSys->getEstanqueOf($message->from);
        $result = new EstanqueReturn($estanque, $paths['hasCotPro']);
        
        $msg = $message->toArray();
        $fSys->setPathBase($paths['chat']);
        $fSys->dumpIn($msg);

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $msg,
            'procesado'=> 'Sin Chat',
            'estanque' => $result->toArray()
        ]);
    }

}
