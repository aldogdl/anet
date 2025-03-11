<?php

namespace App\Service\RasterHub;

class TemplatesTrack
{
    /** */
    public function forTrackOnlyBtnCotizar(String $idImg, String $body, String $idBtn)
    {
        return [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    "image" => ["id" => $idImg]
                ],
                "body" => [
                    "text" => "📣 QUIÉN CON❓:\n"."🚘 *".trim(mb_strtoupper($body))."*". "\n"
                ],
                "footer" => [
                    "text" => "Si cuentas con la pieza, presiona *Cotizar Ahora*"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => 'cotNowWa_'. $idBtn,
                                "title" => "[√] COTIZAR AHORA"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }


  /** */
  public function templateTrackLink(String $link): array {

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
