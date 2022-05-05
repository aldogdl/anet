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
        $path = $this->params->get('targets');
        $lock = $this->lock->createLock('targets');
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
        $path = $this->params->get('targets');
        $lock = $this->lock->createLock('targets');
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

        if(array_key_exists('bundle', $file)) {

            $has = in_array($data['idCamp'], $file['bundle']);
            if($has === false) {
                $file['bundle'][] = $data['idCamp'];
                $result = true;
            }
        }else{
            $file['bundle'][] = $data['idCamp'];
            $result = true;
        }

        if(!array_key_exists('package', $file)) {
            $file['package'] = [];
        }

        if(array_key_exists($data['idCamp'], $file['package'])) {

            $rota = count($file['package'][$data['idCamp']]);
            if($rota > 0) {
                $rota = count($data['package']);
                for ($i=0; $i < $rota; $i++) {
                    if(!in_array( $data['package'][$i], $file['package'][$data['idCamp']] )) {
                        $file['package'][$data['idCamp']][] = $data['package'][$i];
                        $result = true;
                    }
                }
            }else{
                $file['package'][$data['idCamp']] = $data['package'];
                $result = true;
            }
        }else{
            $file['package'][$data['idCamp']] = $data['package'];
            $result = true;
        }

        $rota = count($file['package'][$data['idCamp']]);
        for ($i=0; $i < $rota; $i++) {
            $file['stt'][$data['package'][$i]] = $data['stt'];
        }

        //--  Colocamos la nueva campaña en la sección de no asignadas --
        $file['non'][] = $data['idCamp'];

        if($result) {
            $file['version'] = $data['version'];
            $this->flush($file);
        }

        return $result;
    }

}
