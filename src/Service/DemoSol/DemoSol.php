<?php

namespace App\Service\DemoSol;

use App\Dtos\WaMsgDto;
use App\Service\MyFsys;

class DemoSol {

    private MyFsys $fSys;

    public function __construct(MyFsys $fsys)
    {
        $this->fSys = $fsys;
    }

    /** */
    public function buildMsgTrackDemo(String $to): array
    {
        $demojson = $this->fSys->getContent('demos', 'demo_1.json');
        $trackjson = $this->fSys->getContent('waTemplates', '_track.json');
        if(array_key_exists('type', $trackjson)) {

            $body = $trackjson[$trackjson['type']]['body']['text'];
            $body = str_replace('{:token}', $demojson['title'], $body);
            $body = str_replace('{:detalles}', $demojson['detalles'].'. Demostración', $body);
            $trackjson[$trackjson['type']]['body']['text'] = $body;
            
            $trackjson[$trackjson['type']]['header'] = [
                "type" => "image",
                "image" => ["link" => "https://autoparnet.com/prod_pubs/demo_anet/demo_1.jpg"]
            ];

            $btns = $trackjson[$trackjson['type']]['action']['buttons'];
            $rota = count($btns);
            for ($i=0; $i < $rota; $i++) { 
                $btns[$i][ $btns[$i]['type'] ]['id'] = str_replace(
                    '{:uuid}', 'demo-'.$to, $btns[$i][ $btns[$i]['type'] ]['id']
                );
            }

            $trackjson[$trackjson['type']]['action']['buttons'] = $btns;
        }

        return $trackjson;
    }

    ///
    public function buildBaitDemo(WaMsgDto $waMsg): array
    {
        $demojson = $this->fSys->getContent('demos', 'demo_1.json');
        return [
            "waId"    => $waMsg->from,
            "idItem"  => $waMsg->idDbSr,
            "ownSlug" => $demojson['own']['slug'],
            "wamid"   => $waMsg->context,
            "sended"  => (integer) microtime(true) * 1000,
            "attend"  => 0,
            "stt"     => 2,
            "current" => 'sfto',
            "next"    => 'sfto',
            "mrk"     => $demojson['attrs']['marca'],
            "track"   => [],
        ];
    }
}
