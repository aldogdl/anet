<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;
use App\Service\Pushes;

/**
 * El sistema de rastreo por medio de notificacions para los
 * proveedores.
 */
class TrackProv {

  private array $data;
  private array $contacts;
  private ?Pushes $push;
  private WaSender $waS;

  /** */
  public function __construct(?Pushes $push, WaSender $waS, array $data, array $contacs)
  {
    $this->data = $data;
    $this->contacts = $contacs;
    if($push != null) {
      $this->push = $push;
    }
    $this->waS = $waS;
  }

  /** */
  public function exe(String $folderToBackup, String $folderFails) : array 
  {    
    $result = ['abort' => true, 'msg' => ''];
    $idSendFile = $this->data['type'] .'::'. $this->data['id'] .'::'.round(microtime(true) * 1000);
    $filename = $folderToBackup .$idSendFile. '.json';
    
    if(array_key_exists('slug', $this->contacts)) {
      // Si contiene slug, significa que se le enviará el msg a 1 persona
      $this->data['srcSlug'] = $this->contacts['slug'];
      file_put_contents($filename, json_encode($this->data));
      $this->data['tokens'] = $this->contacts['tokens'];
      $this->data['waIds'] = $this->contacts['waIds'];
    }else{
      // Si NO contiene slug, significa que se le enviará el msg a varias personas
      file_put_contents($filename, json_encode($this->data));
      $this->data['tokens'] = $this->contacts['tokens'];
      $this->data['waIds'] = $this->contacts['waIds'];
    }
    $this->contacts = [];
    file_put_contents('pruebita.json', json_encode($this->data));

    $this->data['cant'] = count($this->data['tokens']);
    if($this->data['cant'] == 0) {
      $result = ['abort' => true, 'msg' => 'X Sin contactos'];
    }else{
        
      // $result = $this->push->sendMultiple($this->data);
      if(array_key_exists('fails', $result)) {
        $filename = $folderFails .
        $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
        $this->data['fails'] = $result['fails'];
        file_put_contents($filename, json_encode($this->data));
        unset($result['fails']);
      }

      if(array_key_exists('idwap', $this->data)) {
        $this->sendToWhatsapp($idSendFile);
      }
    }

    return $result;
  }

  /** */
  private function sendToWhatsapp(String $idFile): void
  {
    $rota = count($this->data['waIds']);
    if($rota == 0) {
      return;
    }
    
    $this->waS->initConmutador();
    if($this->waS->conm == null) {
      return;
    }
    
    for ($i=0; $i < $rota; $i++) {
      // Creamos un archivo que indica al sistema no procesar estatus
      file_put_contents(
        'wa_stt_stop/'.$this->data['waIds'][$i].'.txt',
        round(microtime(true) * 1000)
      );
      $this->waS->setWaIdToConmutador($this->data['waIds'][$i]);
      $isOk = $this->waS->sendPreTemplate( $this->basicTemplateTrack($idFile) );
      file_put_contents( 'wa_envi_'.$isOk.'.txt', '' );
    }
    
  }

  /** */
  private function basicTemplateTrack(String $idFile): array
  {
    return [
      "type" => "interactive",
      "interactive" => [
        "type" => "button",
        "header" => [
          "type" => "image",
          "image" => ["id" => $this->data['idwap']]
        ],
        "body" => [
          "text" => $this->data['title'] . $this->data['body'] . "\n"
        ],
        "footer" => [
          "text" => "¿Cómo quieres Cotizar?"
        ],
        "action" => [
          "buttons" => [
            [
              "type" => "reply",
              "reply" => [
                "id" => 'cotDirect_'. $idFile,
                "title" => "Directamente"
              ]
            ],
            [
              "type" => "reply",
              "reply" => [
                "id" => 'cotForm_'. $idFile,
                "title" => "Formulario"
              ]
            ]
          ]
        ]
      ]
    ];
  }

  /** */
  private function templateTrackLink(String $link, array $data): array {

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
          "para continuar cotizando la pieza \n" .
          $data['body'] . "\n"
        ],
        "footer" => [
          "text" => "Un servicio más de RasterFy"
        ],
        "action" => [
          [
            "name" => "cta_url",
            "parameters" => [
              "display_text" => "Presiona Aquí",
              "url" => $link
            ]
          ]
        ]
      ]
    ];
  }

}
