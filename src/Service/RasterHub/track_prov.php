<?php

namespace App\Service\RasterHub;

use App\Service\Pushes;

/**
 * El sistema de rastreo por medio de notificacions para los
 * proveedores
 */
class TrackProv {

    private array $data;
    private array $contacts;
    private Pushes $push;
    public function __construct(Pushes $push, array $data, array $contacs)
    {
        $this->data = $data;
        $this->contacts = $contacs;
        $this->push = $push;
    }

    /** */
    public function exe(String $folderToBackup, String $folderFails) : array {
        
        $result = ['abort' => true, 'msg' => ''];
        $filename = $folderToBackup .
            $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
        
        if(array_key_exists('slug', $this->contacts)) {
            $this->data['srcSlug'] = $this->contacts['slug'];
            file_put_contents($filename, json_encode($this->data));
            $this->data['tokens'] = $this->contacts['tokens'];
        }else{
            file_put_contents($filename, json_encode($this->data));
            $this->data['tokens'] = $this->contacts;
        }
        
        $this->data['cant'] = count($this->data['tokens']);
        if($this->data['cant'] == 0) {
            $result = ['abort' => true, 'msg' => 'X Sin contactos'];
        }else{
            
            $result = $this->push->sendMultiple($this->data);
            file_put_contents('wa_pruebita.json', json_encode($this->data));

            if(array_key_exists('fails', $result)) {
                $filename = $folderFails .
                    $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
                    $this->data['fails'] = $result['fails'];
                file_put_contents($filename, json_encode($this->data));
                unset($result['fails']);
            }
        }

        return $result;
    }
}