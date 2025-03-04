<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;

class FormatQC
{
    /** */
    public function build(WaMsgDto $msg) : array
    {
        $body = mb_strtolower($msg->content['caption']);
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
                    "image" => ["id" => $msg->content['id']]
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
                                "title" => "[ X ] EN DIRECTO"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
