<?php

namespace App\Service\RasterHub;

use App\Service\ItemTrack\WaSender;
use App\Service\Pushes;

/**
 * El sistema de rastreo por medio de notificacions para los
 * proveedores.
 */
class TrackProv {

    private array $data;
    private array $contacts;
    private Pushes $push;
    private WaSender $waS;

    public function __construct(Pushes $push, WaSender $waS, array $data, array $contacs)
    {
        $this->data = $data;
        $this->contacts = $contacs;
        $this->push = $push;
        $this->waS = $waS;
    }

    /** */
    public function exe(String $folderToBackup, String $folderFails) : array {
        
        $result = ['abort' => true, 'msg' => ''];
        $filename = $folderToBackup .
            $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
        
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

        $this->data['cant'] = count($this->data['tokens']);
        if($this->data['cant'] == 0) {
            $result = ['abort' => true, 'msg' => 'X Sin contactos'];
        }else{
            
            $result = $this->push->sendMultiple($this->data);
            if(array_key_exists('fails', $result)) {
                $filename = $folderFails .
                $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
                $this->data['fails'] = $result['fails'];
                file_put_contents($filename, json_encode($this->data));
                unset($result['fails']);
            }
            if(array_key_exists('idwap', $this->data)) {
                $this->sendToWhatsapp();
            }
        }

        return $result;
    }

    /** */
    private function sendToWhatsapp() {

        $rota = count($this->data['waIds']);
        if($rota == 0) {
            return;
        }
        $this->waS->initConmutador();
        if($this->waS->conm != null) {
            for ($i=0; $i < $rota; $i++) { 
                $this->waS->setWaIdToConmutador($this->data['waIds'][$i]);
                $this->waS->sendPreTemplate( $this->basicTemplate() );
            }
        }
    }

    /** */
    private function basicTemplate(): array {

        return [
            "type" => "interactive",
            "interactive" => [
              "type" => "button",
              "header" => [
                "type" => "image",
                "image" => ["id" => $this->data['idwap']]
              ],
              "body" => [
                "text" => $this->data['title'] . "\b" . $this->data['body'] . "\b\b" . 
                "https://wa.me/" . $this->data['ownWaId'] . "?text=prueba%20de%20comunicación"
              ],
              "footer" => [
                "text" => "Enviado desde RasterFy"
              ],
              "action" => [
                "buttons" => [
                  [
                    "type" => "reply",
                    "reply" => [
                      "id" => $this->data['type'] . "_" . $this->data['idDbSr'],
                      "title" => "Formulario"
                    ]
                  ]
                ]
              ]
            ]
        ];
    }
}