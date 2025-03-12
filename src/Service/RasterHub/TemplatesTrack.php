<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;

class TemplatesTrack
{
    
    /** */
    public function forTrackOnlyBtnCotizar(
        String $idImg, String $body, String $idBtn, String $from = 'qc'
    ) {
        $botones = [
            [
                "type" => "reply",
                "reply" => [
                    "id" => 'cotNowWa_'. $idBtn,
                    "title" => "[X] EN DIRECTO"
                ]
            ]
        ];
    
        if($from == 'form') {
            $botones[] = [
                "type" => "reply",
                "reply" => [
                "id" => 'cotNowFrm_'. $idBtn,
                "title" => "[√] FORMULARIO"
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
                    "text" => "Si cuentas con la pieza, presiona *Cotizar Ahora*"
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
  public function buildTemplateLinkAction(WaMsgDto $msg, String $waIdEmisor, String $body) : array 
  {
    $link = '';
    if($msg->subEvento == 'cotNowWa') {

      $text = "Hola qué tal!!.👍\n".
      "Con respecto a la solicitud de Cotización para:\n".
      "🚘 *".trim(mb_strtoupper($body))."*\n".
      "Te envío Fotos y Costo:\n";

      $link = 'https://wa.me/'.$waIdEmisor."?text=".urlencode($text);
    }else{
      // Código del btn cotformpp
      $file = [];
      $link = 'https://autoparnet.com/form/cotiza/item?idItem='.$file['idItem'].'&idDbSr='.$file['idDbSr'];
    }

    $tmp = new TemplatesTrack();
    return $tmp->templateTrackLink($link);
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
