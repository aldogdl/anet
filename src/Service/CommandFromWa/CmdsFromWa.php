<?php

namespace App\Service\CommandFromWa;

class CmdsFromWa {

    public String $cmd;
    public String $path;

    public function __construct(String $elCmd, String $pathToSave)
    {
        $this->cmd = $elCmd;
        $this->path = $pathToSave;
        $this->execute();
    }

    ///
    private function execute() : void
    {    
        // El usuario solicita los datos de la pieza
        if(mb_strpos($this->cmd, 'gpz') !== false) {
            $filename = str_replace('|', '-', $this->cmd);
            file_put_contents($this->path.'/'.trim($filename).'.gpz', '');
        }
    }

}
