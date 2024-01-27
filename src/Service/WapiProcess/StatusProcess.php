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

        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $message->toArray(),
            'estanque' => $result->toArray()
        ]);
    }

}
