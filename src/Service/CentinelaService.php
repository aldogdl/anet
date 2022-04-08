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

class CentinelaService
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
    public function getSchemaInit(array $rutaPieza, string $ruta): array
    {
        return [
            'stt' => [
                'est' => $rutaPieza['est'],
                'stt' => $rutaPieza['stt'],
                'ruta' => $ruta
            ]
        ];
    }

    /** */ 
    public function getSchemaOrdenInit(string $key, array $data): array
    {
        return [
            $key => [
                'est' => $data['est'],
                'stt' => $data['stt'],
                'ruta'=> $data['rta']
            ]
        ];
    }

    /** */
    public function setNewOrden(array $data): bool
    {
        $dataMain = [];
        $result = false;
        $path = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if ($lock->acquire(true)) {

            if($this->filesystem->exists($path)) {
                $dataMain = json_decode( file_get_contents($path), true );
            }

            if(array_key_exists('ordenes', $dataMain)) {

                $has = in_array($data['idOrden'], $dataMain['ordenes']);
                if($has === false) {
                    $dataMain['ordenes'][] = $data['idOrden'];
                    $result = true;               
                }
            }else{
                $dataMain['ordenes'][] = $data['idOrden'];
                $result = true;
            }

            if(!array_key_exists('piezas', $dataMain)) {
                $dataMain['piezas'] = [];
            }

            if(array_key_exists($data['idOrden'], $dataMain['piezas'])) {

                $rota = count($dataMain['piezas'][$data['idOrden']]);
                if($rota > 0) {
                    $rota = count($data['piezas']);
                    for ($i=0; $i < $rota; $i++) { 
                        if(!in_array( $data['piezas'][$i], $dataMain['piezas'][$data['idOrden']] )) {
                            $dataMain['piezas'][$data['idOrden']][] = $data['piezas'][$i];
                            $result = true;
                        }
                    }
                }else{
                    $dataMain['piezas'][$data['idOrden']] = $data['piezas'];
                    $result = true;
                }
            }else{
                $dataMain['piezas'][$data['idOrden']] = $data['piezas'];
                $result = true;
            }

            $rota = count($dataMain['piezas'][$data['idOrden']]);
            for ($i=0; $i < $rota; $i++) { 
                $dataMain['stt'][$data['piezas'][$i]] = $data['stt'];
            }
        }
        $lock->release();
        if($result) {
            $dataMain['version'] = $data['version'];
            $this->filesystem->dumpFile($path, json_encode($dataMain));
        }
        return $result;
    }

    /** */
    public function setNewSttToOrden(array $data): bool
    {
        $dataMain = [];
        $result = false;
        $path = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if ($lock->acquire(true)) {

            if($this->filesystem->exists($path)) {
                $dataMain = json_decode( file_get_contents($path), true );
                if(!array_key_exists('ord', $dataMain)) {
                    $dataMain['ord'] = [];
                }
                if(!array_key_exists($data['orden'], $dataMain['ord'])) {
                    $dataMain['ord'][] = $this->getSchemaOrdenInit($data['orden'], $data);
                }else{
                    $dataMain['ord'][$data['orden']] = [
                        'est' => $data['est'],
                        'stt' => $data['stt'],
                        'ruta'=> $data['rta']
                    ];
                }
                $dataMain['version'] = $data['version'];
                $this->filesystem->dumpFile($path, json_encode($dataMain));
            }
        }

        $lock->release();
        return $result;
    }

    /**
     * Checamos la version del centinela para ver si hay cambios
    */
    public function isSameVersion(string $oldVersion): bool
    {
        $dataMain = [];
        $result = false;
        $ruta = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if ($lock->acquire(true)) {

            if($this->filesystem->exists($ruta)) {
                $dataMain = json_decode( file_get_contents($ruta), true );
            }
            $lock->release();
            if(array_key_exists('version', $dataMain)) {
                if($dataMain['version'] == $oldVersion) {
                    $result = true;
                }
            }
        }

        return $result;
    }

}