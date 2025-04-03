<?php

namespace App\Service;

use App\Service\MyFsys;

class SincronizerItem
{

    private MyFsys $fSys;

    public function __construct(MyFsys $fs)
    {
        $this->fSys = $fs;    
    }

    /** */
    public function build(array $data): void
    {
        $timestamp = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        // Recuperamos el archivo
        $content = $this->fSys->getContent('sincDev', $data['ownWaId'].'.json');
        if(count($content) == 0) {
            $content = [
                $timestamp.'' => [$data['id']]
            ];
        }else{
            if(array_key_exists($timestamp, $content)) {
                $content[$timestamp][] = $data['id'];
            }
        }
        $this->fSys->setContent('sincDev', $data['ownWaId'].'.json', $content);
    }

}
