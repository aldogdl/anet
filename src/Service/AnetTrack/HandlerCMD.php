<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaSender;
use App\Service\AnetTrack\WaInitSess;

class HandlerCMD
{
    private Fsys $fSys;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $cmds = [
        "login" => [
            "desc" => "Forzamos un nuevo inicio de sesion"
        ],
        "td" => [
            "desc" => "Retornamos la tarjeta digital del usuario"
        ],
        "pausa" => [
            "desc" => "Solicitar poner en pausa tu cuenta para no enviarte más mensajes"
        ],
        "pause" => [
            "desc" => "Solicitar poner en pausa tu cuenta para no enviarte más mensajes",
            "hide" => "No mastrar este cmd a los usuarios"
        ],
        "play" => [
            "desc" => "Reanudar el envio de mensajes."
        ],
    ];

    /** */
    public function __construct(Fsys $fsys, WaSender $waS, WaMsgDto $msg)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->waSender->setConmutador($this->waMsg);
    }

    /** */
    public function exe(): void
    {

        if($this->waMsg->content == 'login') {

            $this->fSys->delete('/', $this->waMsg->from.'_iniLogin.json');
            $this->waMsg->tipoMsg = TypesWaMsgs::LOGIN;
            $this->waMsg->subEvento = 'iniLogin';
            $clase = new WaInitSess($this->fSys, $this->waSender, $this->waMsg);
            $clase->exe();
            return;

        }elseif($this->waMsg->content == 'td') {

            $this->waSender->sendText(
                "😃👍 *TARGETA DIGITAL*\n\n".
                "Estamos procesando tu solicitud, un momento por favor."
            );
            $retornar = [
                'evento' => 'whatsapp_api',
                'payload' => ['subEvent' => 'cmd', 'cmd' => 'td', 'waId' => $this->waMsg->from]
            ];
            $this->waSender->sendMy($retornar);
            return;
        }elseif($this->waMsg->content == 'pausa' || $this->waMsg->content == 'pause') {

            $this->waSender->sendText(
                "😃👍 *SOLICITUD RECIBIDA*\n\n".
                "Cuando estés listo, ingresa el comando ".
                "*anet play*, para reanudar los envíos."
            );
            $retornar = [
                'evento' => 'whatsapp_api',
                'payload' => ['subEvent' => 'cmd', 'cmd' => 'pausa', 'waId' => $this->waMsg->from]
            ];
            $this->waSender->sendMy($retornar);
            return;
        }elseif($this->waMsg->content == 'play') {

            $this->waSender->sendText(
                "😃👍 *REANUDANDO ENVÍOS*\n\n".
                "Pronto recibirás más oportunidades de Venta".
                ""
            );
            $retornar = [
                'evento' => 'whatsapp_api',
                'payload' => ['subEvent' => 'cmd', 'cmd' => 'play', 'waId' => $this->waMsg->from]
            ];
            $this->waSender->sendMy($retornar);
            return;
        }

        $this->waSender->sendText(
            "⚠️ El Comando [*".$this->waMsg->content."*] no existe entre los ".
            "comando permitidos en Autoparnet"
        );
        return;
    }

}
