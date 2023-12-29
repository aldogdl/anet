<?php

namespace App\Service\WapiResponse;

use Symfony\Component\Filesystem\Filesystem;

class FsysProcess
{
    private Filesystem $fSys;
    private String $path;
    private String $filename;

    public String $hasErr = '';
    public String $toChat = '';

    /** */
    public function __construct(String $pathChat) {

        $this->fSys = new Filesystem();
        if(!$this->fSys->exists($pathChat)) {
            $this->fSys->mkdir($pathChat);
        }
        $this->toChat = $pathChat;
    }

    /** */
    private function setRoot(array $content) {

        if(array_key_exists('from', $content)) {
            if(array_key_exists('id', $content)) {
                $this->filename = $content['id'] .'.json';
                if(array_key_exists('recibido', $content)) {
                    $this->path = $this->toChat.$content['from'].'/'.$content['recibido'];
                }
            }
        }
    }

    /** */
    public function get(array $content): array
    {
        if(count($content) == 0) {
            return [];
        }
        
        $this->setRoot($content);
        if($this->filename == '' || $this->path == '') {
            return [];
        }
        return json_decode( file_get_contents($this->path.'/'.$this->filename), true);
    }

    /** */
    public function dumpIn(array $content): void
    {
        if(count($content) == 0) {
            return;
        }
        $this->setRoot($content);
        if($this->filename == '' || $this->path == '') {
            return;
        }
        $this->fSys->dumpFile($this->path.'/'.$this->filename, json_encode($content));
    }
}
