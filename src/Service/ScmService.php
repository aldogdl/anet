<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class ScmService
{
  private $name = 'targets';
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
  public function getContent(bool $clean = false): array
  {
    $msgs = [];
    $path = $this->params->get($this->name);
    $lock = $this->lock->createLock($this->name);
    if ($lock->acquire(true)) {
      if($this->filesystem->exists($path)) {
        $msgs = json_decode( file_get_contents($path), true );
      }
    }

    $lock->release();
    if($clean) {
      if(count($msgs) > 0) {
        $this->flush([]);
      }
    }
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

  /** */
  public function flush(array $file)
  {
    $path = $this->params->get($this->name);
    $lock = $this->lock->createLock($this->name);
    if($lock->acquire(true)) {
      $this->filesystem->dumpFile($path, json_encode($file));
    }
    $lock->release();
  }

  /**
   * @see
  */
  public function setNewMsg(array $data)
  {
    $file = $this->getContent();
    $result = false;

    if(!array_key_exists($data['target'], $file)) {
      $file[$data['target']][] = $data['idCamp'];
      $result = true;
    }else{
      $has = in_array($data['idCamp'], $file[$data['target']]);
      if($has === false) {
        $file[$data['target']][] = $data['idCamp'];
        $result = true;
      }
    }
    if($result) {
      $this->flush($file);
    }
  }

}
