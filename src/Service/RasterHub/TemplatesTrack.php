<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;

class TemplatesTrack
{
    /** */
    public function forTrackOnlyBtnCotizar(
        String $idImg, String $body, String $idBtn, String $from = 'qc'
    ) {

        $foot = "Si cuentas con la pieza, presiona *Cotizar Ahora*";
        $botones = [
            [
                "type" => "reply",
                "reply" => [
                    "id" => 'cotNowWa_'. $idBtn,
                    "title" => "[√] COTIZAR AHORA"
                ]
            ]
        ];

        if($from == 'form') {
            $foot = "Selecciona la vía por la que deseas *Cotizar Ahora*";
            $botones = [
                [
                    "type" => "reply",
                    "reply" => [
                        "id" => 'cotNowWa_'. $idBtn,
                        "title" => "[->] EN DIRECTO"
                    ]
                ],
                [
                    "type" => "reply",
                    "reply" => [
                        "id" => 'cotNowFrm_'. $idBtn,
                        "title" => "[√] FORMULARIO"
                    ]
                ]
            ];
        }

        return [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    "image" => ["id" => $idImg]
                ],
                "body" => [
                    "text" => "📣 QUIÉN CON❓:\n".
                    "🚘 *".trim(mb_strtoupper($body))."*\n"
                ],
                "footer" => [
                    "text" => $foot
                ],
                "action" => [
                    "buttons" => $botones
                ]
            ]
        ];
    }

    /** 
     * Metodo para construir el boton para contactar al solicitante cuando se presionó
     * el boton de cotizar ahora.
     */
    public function buildTemplateLinkAction(
        WaMsgDto $msg, String $waIdEmisor, array $file
    ) : array {

        $link = '';
        if($msg->subEvento == 'cotNowWa') {

            $text = "Hola qué tal!!.👍\n".
            "Con respecto a la solicitud de Cotización:\n".
            "🚘 *".trim(mb_strtoupper($file['body']))."*\n".
            "Te envío Fotos y Costo:\n";

            $link = 'https://wa.me/'.$waIdEmisor."?text=".urlencode($text);
        }else{
            // Código del btn cotformpp
            $link = 'https://autoparnet.com/form/cotiza/item?idItem='.$file['id'].'&idDbSr='.$file['idDbSr'];
        }

        return [
            "type" => "interactive",
            "interactive" => [
                "type" => "cta_url",
                "header" => [
                    "type" => "text",
                    "text" => "Atendiendo a tu Solicitud!"
                ],
                "body" => [
                    "text" => "Presiona el Botón de la parte inferior " . 
                    "para *CONTINUAR* con el proceso.\n"
                ],
                "footer" => [
                    "text" => "Un servicio más de YonkesMX"
                ],
                "action" => [
                    "name" => "cta_url",
                    "parameters" => [
                        "display_text" => "Presiona Aquí",
                        "url" => $link
                    ]
                ]
            ]
        ];
    }

}
