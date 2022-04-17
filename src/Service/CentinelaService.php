<?php

namespace App\Service;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

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

    /**
     * El esqueleto para una pieza nueva
     */ 
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

    /**
     * El esqueleto para una orden nueva usada para el elemento -ord- dentro del centinela
     * el cual indican los status de las ordenes en si.
     */ 
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
    public function downloadCentinela(): array
    {
        $dataMain = [];
        $path = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if($this->filesystem->exists($path)) {

            if ($lock->acquire(true)) {
                $dataMain = json_decode( file_get_contents($path), true );
            }
        }
        $lock->release();
        return $dataMain;
    }

    /**
     * @see [Cotiza] GetController::enviarOrden
    */
    public function setNewOrden(array $data): bool
    {
        $file = [];
        $result = false;
        $path = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if ($lock->acquire(true)) {

            if($this->filesystem->exists($path)) {
                $file = json_decode( file_get_contents($path), true );
            }

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
        }

        if($result) {
            $file['version'] = $data['version'];
            $this->filesystem->dumpFile($path, json_encode($file));
        }
        $lock->release();
        return $result;
    }

    /**
     * @see [SCP] CentinelaController::enviarOrden
    */
    public function asignarOrdenes(array $asignaciones)
    {
        $file = [];
        $path = $this->params->get('centinela');
        $lock = $this->lock->createLock('centinela');
        if ($lock->acquire(true)) {

            if($this->filesystem->exists($path)) {
                $file = json_decode( file_get_contents($path), true );
            }
            if(count($file) > 0) {
                if(array_key_exists('avo', $file)) {
                    foreach ($asignaciones['info'] as $idAvo => $ords) {
                        $file['avo'][$idAvo] = $ords;
                    }
                }else{
                    $file['avo'] = $asignaciones['info'];
                }
                foreach ($asignaciones['info'] as $idAvo => $ords) {
                    $file['non'] = array_diff($file['non'], $ords);
                }
                $file = $this->updateVersion(
                    $asignaciones['version'],
                    $asignaciones['manifest'],
                    $file
                );
                $this->filesystem->dumpFile($path, json_encode($file));
            }
        }
        $lock->release();
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

    ///
    private function updateVersion(int $version, array $manifest, array $centinela): array
    {
        $centinela['version'] = $version;
        $centinela['manifest'] = $manifest;
        return $centinela;
    }
}