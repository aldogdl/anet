<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class ScmService
{   

    private $params;
    private $filesystem;
    private $lock;

    public function __construct(ParameterBagInterface $container)
    {
        $this->params = $container;
        $this->filesystem = new Filesystem();
        $this->init();
    }

    /** */
    private function init() 
    {
        $pathLock = $this->params->get('datafix');
        $store = new FlockStore($pathLock);
        $this->lock = new LockFactory($store);
    }

    /**
     * 
    */
    public function getContent(): array
    {
        $msgs = [];
        $path = $this->params->get('scm');
        $lock = $this->lock->createLock('scm');
        if ($lock->acquire(true)) {
            if($this->filesystem->exists($path)) {
                $msgs = json_decode( file_get_contents($path), true );
            }
        }
        $lock->release();
        return $msgs;
    }
    
    /**
     * 
    */
    public function clean(String $campo)
    {
        $content = $this->getContent();
        $content[$campo] = [];
        $this->flush($content);
    }
    
    /**
     * 
    */
    public function flush(array $file)
    {
        $path = $this->params->get('scm');
        $lock = $this->lock->createLock('scm');
        if ($lock->acquire(true)) {
            $this->filesystem->dumpFile($path, json_encode($file));
        }
        $lock->release();
    }

    /**
     * @see 
    */
    public function setNewMsg(array $data): bool
    {
        $file = $this->getContent();
        $result = false;

        if(array_key_exists('ordenes', $file)) {

            $has = in_array($data['idOrden'], $file['ordenes']);
            if($has === false) {
                $file['ordenes'][] = $data['idOrden'];
                $result = true;               
            }
        }else{
            $file['ordenes'][] = $data['idOrden'];
            $result = true;
        }

        if(!array_key_exists('piezas', $file)) {
            $file['piezas'] = [];
        }

        if(array_key_exists($data['idOrden'], $file['piezas'])) {

            $rota = count($file['piezas'][$data['idOrden']]);
            if($rota > 0) {
                $rota = count($data['piezas']);
                for ($i=0; $i < $rota; $i++) { 
                    if(!in_array( $data['piezas'][$i], $file['piezas'][$data['idOrden']] )) {
                        $file['piezas'][$data['idOrden']][] = $data['piezas'][$i];
                        $result = true;
                    }
                }
            }else{
                $file['piezas'][$data['idOrden']] = $data['piezas'];
                $result = true;
            }
        }else{
            $file['piezas'][$data['idOrden']] = $data['piezas'];
            $result = true;
        }

        $rota = count($file['piezas'][$data['idOrden']]);
        for ($i=0; $i < $rota; $i++) { 
            $file['stt'][$data['piezas'][$i]] = $data['stt'];
        }

        //--  Colocamos la nueva orden en la sección de no asignadas --
        $file['non'][] = $data['idOrden'];

        if($result) {
            $file['version'] = $data['version'];
            $this->flush($file);
        }
        
        return $result;
    }

}