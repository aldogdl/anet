<?php

namespace App\Service\DemoSol;

use App\Service\AnetTrack\Fsys;

class DemoSol {

    private Fsys $fSys;
    private String $waIdTo;

    public function __construct(Fsys $fsys)
    {
        $this->fSys = $fsys;
    }

    /** */
    public function exe(): array
    {    
        return $this->buildMsgTrackDemo();
    }

    /** */
    private function buildMsgTrackDemo(): array
    {
        $demojson = $this->fSys->getContent('demos', 'demo_1.json');
        $trackjson = $this->fSys->getContent('waTemplates', '_track.json');
        if(array_key_exists('type', $trackjson)) {

            $body = $trackjson[$trackjson['type']]['body']['text'];
            $body = str_replace('{:token}', $demojson['title'], $body);
            $body = str_replace('{:detalles}', $demojson['detalles'], $body);
            $trackjson[$trackjson['type']]['body']['text'] = $body;
            
            $trackjson[$trackjson['type']]['header'] = [
                "type" => "image",
                "image" => ["link" => "https://autoparnet.com/prod_pubs/demo_anet/demo_1.jpg"]
            ];

            $btns = $trackjson[$trackjson['type']]['action']['buttons'];
            $rota = count($btns);
            for ($i=0; $i < $rota; $i++) { 
                $btns[$i][ $btns[$i]['type'] ]['id'] = str_replace(
                    '{:uuid}', 'demo_'.$this->waIdTo, $btns[$i][ $btns[$i]['type'] ]['id']
                );
            }

            $trackjson[$trackjson['type']]['action']['buttons'] = $btns;
        }

        return $trackjson;
    }
}