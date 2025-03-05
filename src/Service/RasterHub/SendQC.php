<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;
use App\Repository\FcmRepository;
use App\Service\ItemTrack\WaSender;
use App\Service\MyFsys;

class SendQC
{
    private WaSender $waSender;
    private MyFsys $fsys;
    private FcmRepository $fcmEm;
    private WaMsgDto $msg;

    /** */
    public function __construct(FcmRepository $fbm, MyFsys $fSys, WaSender $waS)
    {
        $this->fsys = $fSys;
        $this->fcmEm = $fbm;
        $this->waSender = $waS;
    }

    /** */
    public function exe(WaMsgDto $msg) : void 
    {
        $this->msg = $msg;
        $this->waSender->setConmutador($this->msg);
        $template = $this->build();
        if(count($template) == 0) {

            $this->waSender->sendText(
                'U olvidaste el comando *#qc* o tu mensaje es inválido'
            );
            return;
        }
        $this->waSender->sendPreTemplate($template);
    }

    /** */
    public function build() : array
    {
        $body = mb_strtolower($this->msg->content['caption']);
        $idFile = time() * 1000;
        $partes = explode(' ', $body);
        $rota = count($partes);
        $cuerpo = [];

        for ($i=0; $i < $rota; $i++) { 
            if($partes[$i] == '#') {
                continue;
            }
            if($partes[$i] == '#qc') {
                continue;
            }
            if($partes[$i] == 'qc') {
                continue;
            }
            $cuerpo[] = $partes[$i];
        }

        $body = implode(' ', $cuerpo);

        return [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    "image" => ["id" => $this->msg->content['id']]
                ],
                "body" => [
                    "text" => "🚘 Quién con:\n"."*".trim($body)."*". "\n"
                ],
                "footer" => [
                    "text" => "¿Cómo quieres Cotizar?"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => 'ntgapp_'. $idFile,
                                "title" => "NO Vendo la Marca"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => 'cotdirpp_'. $idFile,
                                "title" => "[√] COTIZAR AHORA"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
