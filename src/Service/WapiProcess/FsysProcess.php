<?php

namespace App\Service\WapiProcess;

use Symfony\Component\Filesystem\Filesystem;

class FsysProcess
{
    private Filesystem $fSys;
    private String $path;
    private String $filename;

    public String $hasErr = '';
    public String $pathBase = '';

    /** 
     * insertamos un path el cual funje como base(root) para
     * los demas metodos
    */
    public function __construct(String $pathIn) {
        $this->buildPath($pathIn);
    }

    /**
     * Usado para insertar un nuevo pathBase y usarlo como constructor, es decir.
    */
    public function setPathBase(String $pathIn) {
        $this->buildPath($pathIn);
    }

    /** */
    private function buildPath(String $pathIn) {
        $this->fSys = new Filesystem();
        if(!$this->fSys->exists($pathIn)) {
            $this->fSys->mkdir($pathIn);
        }
        $this->pathBase = $pathIn;
    }

    /**
     * Recuperamos el archivo de tracked del cotizador
     */
    public function getTrackedsFileOf(String $waId): array { return $this->getContent($waId.'.json'); }

    /**
     * Recuperamos el archivo Estanque del cotizador
     */
    public function getEstanqueOf(String $waId): array { return $this->getContent($waId.'.json'); }

    /**
     * Recuperamos el contenido de un archivo
     */
    public function getContent(String $filename): array
    {
        $pathTo = $this->pathBase.'/'.$filename;
        if($this->fSys->exists($pathTo)) {
            $content = file_get_contents($pathTo);
            if($content != '') {
                return json_decode($content, true);
            }
        }
        return [];
    }

    /**
     * Colocamos el contenido dentro de un archivo.
    */
    public function setContent(String $filename, array $content): void
    {
        if(count($content) == 0) {
            return;
        }
        if($filename == '' || $this->pathBase == '') {
            return;
        }
        $this->fSys->dumpFile($this->pathBase.'/'.$filename, json_encode($content));
    }

    /**
     * Borramos un archivo
    */
    public function delete(String $filename): void
    {
        if($filename == '' || $this->pathBase == '') {
            return;
        }
        $this->fSys->remove($this->pathBase.'/'.$filename);
    }

    /**
     * Construimos el sistema de archivos (serie de carpetas) para almacenar los
     * chats (Mensajes recibidos y enviados desde y para whatsapp)
    */
    private function setRootChat(array $content) {

        if(array_key_exists('from', $content)) {
            if(array_key_exists('id', $content)) {
                $this->filename = $content['id'] .'.json';
                if(array_key_exists('recibido', $content)) {
                    $this->path = $this->pathBase.'/'.$content['from'].'/'.$content['recibido'];
                }
            }
        }
    }

    /**
     * Recuperamos un mensaje de Chat para actualizar sus datos
    */
    public function getChat(array $content): array
    {
        if(count($content) == 0) {
            return [];
        }
        
        $this->setRootChat($content);
        if($this->filename == '' || $this->path == '') {
            return [];
        }
        $filename = $this->path.'/'.$this->filename;
        if(is_file($filename)) {
            try {
                return json_decode( file_get_contents($filename), true);
            } catch (\Throwable $th) {}
        }
        return [];
    }

    /**
     * Vaciamos el contenido dentro de un archivo. el path y filename son determinados por
     * @see $this->setRootChat.
    */
    public function dumpIn(array $content): void
    {
        if(count($content) == 0) {
            return;
        }
        $this->setRootChat($content);
        if($this->filename == '' || $this->path == '') {
            return;
        }
        $this->fSys->dumpFile($this->path.'/'.$this->filename, json_encode($content));
    }

}
