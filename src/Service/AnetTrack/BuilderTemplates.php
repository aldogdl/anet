<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;

class BuilderTemplates {

    private Fsys $fSys;
    private WaMsgDto $waMsg;

    public function __construct(Fsys $fsys, WaMsgDto $wamsg)
    {
        $this->fSys = $fsys;
        $this->waMsg = $wamsg;
    }

    /** */
    public function exe(String $template): array
    {
        $content = $this->fSys->getContent('waTemplates', $template.'.json');
        if(count($content) > 0) {
            switch ($template) {
                case 'sfto':
                    $content = $this->forSFTO($content);
                    break;
                
                default:
                    $content = [];
                    break;
            }
        }

        return $content;
    }

    /** */
    private function forSFTO(array $tmp): array
    {
        if(array_key_exists('interactive', $tmp)) {
            $id = $tmp['interactive']['action']['buttons'][0]['reply']['id'];
            $id = str_replace('{:uuid}', $this->waMsg->idItem, $id); 
            $tmp['interactive']['action']['buttons'][0]['reply']['id'] = $id;
            return $tmp;
        }
        return [];
    }
}