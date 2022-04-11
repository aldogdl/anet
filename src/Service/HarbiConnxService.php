<?php

namespace App\Service;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\RetryTillSaveStore;

class HarbiConnxService
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

    /** */
    public function saveIp(array $data)
    {
        $isNew = true;
        $connxs = $this->get();
        if(count($connxs) > 0) {
            if(array_key_exists($data['key'], $connxs)) {
                $connxs[$data['key']] = $data['conx'];
                $isNew = false;
            }
        }
        if($isNew) {
            $connxs[] = [$data['key'] =>  $data['conx']];
        }
        $this->set($connxs);
    }

    /** */
    public function get(): array
    {
        $dataMain = [];
        $path = $this->params->get('harbiConnx');
        if($this->filesystem->exists($path)) {
            $dataMain = json_decode( file_get_contents($path), true );
        }
        return $dataMain;
    }

    /** */
    public function set(array $data)
    {
        $path = $this->params->get('harbiConnx');
        $lock = $this->lock->createLock('harbiConnx');
        if ($lock->acquire(true)) {
            $this->filesystem->dumpFile($path, json_encode($data));
        }
        $lock->release();
    }
}