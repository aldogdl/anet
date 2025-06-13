<?php

namespace App\Service\Any\Fsys;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class Fsys {

    private Filesystem $filesystem;
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $contenedor)
    {
        $this->params = $contenedor;
        $this->filesystem = new Filesystem();
    }
    
    /** */
    public function _parsePath(String $path, ?String $filename)
    {
        $full = Path::canonicalize($this->params->get($path));
        if(!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        if($filename != null && mb_strpos($filename, '+') !== false) {
            $filename = str_replace('+', '/', $filename);
            return Path::canonicalize($full.'/'.$filename);
        }
        return $full;
    }

    /** */
    public function getPackageOfDay(): array
    {
        try {
            $content = file_get_contents('pakage.json');
            if($content) {
                return json_decode($content, true);
            }
        } catch (\Throwable $th) {}
        return [];
    }

    /** */
    public function setPackageOfDay(array $pakage): array
    {
        file_put_contents('pakage.json', json_encode($pakage));
        return [];
    }

    /** */
    public function getDiccionary(): array
    {
        $path = Path::canonicalize($this->params->get(AnyPath::$DICC));
        if($this->filesystem->exists($path)) {
            $content = file_get_contents($path);
            if($content) {
                $bytes = mb_strlen($content);
                $decode = json_decode($content, true);
                if($bytes > 1700) {
                    file_put_contents($path, json_encode($decode));
                }
                return $decode;
            }
        }
        return [];
    }
    
    /** */
    public function get(String $path, ?String $filename) : array
    {
        $path = $this->_parsePath($path, $filename);
        if($this->filesystem->exists($path)) {
            $content = file_get_contents($path);
            if($content) {
                return json_decode($content, true);
            }
        }
        return [];
    }

    /** */
    public function set(String $path, array $content, ?String $filename) : String
    {
        $path = $this->_parsePath($path, $filename);
        try {
            $this->filesystem->dumpFile($path, json_encode($content));
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
        return '';
    }
}