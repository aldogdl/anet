<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class FiltrosService
{

  private $name = 'filtro';
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
    $items = [];
    $path = $this->params->get($this->name);
    $lock = $this->lock->createLock($this->name);
    if ($lock->acquire(true)) {
      if($this->filesystem->exists($path)) {
        $items = json_decode( file_get_contents($path), true );
        if($items == null) {
          $items = [];
        }
      }
    }
    if($clean) { $this->flush([]); }

    $lock->release();
    return $items;
  }

  /**
   *
  */
  public function flush(array $file)
  {
    $path = $this->params->get($this->name);
    $lock = $this->lock->createLock($this->name);
    if ($lock->acquire(true)) {
      $this->filesystem->dumpFile($path, json_encode($file));
    }
    $lock->release();
  }

  /** */
  public function setNew(int $idFiltro): bool
  {
    $file = $this->getContent();
    $result = false;
    if(!in_array($idFiltro, $file)) {
      $file[] = $idFiltro;
      $result = true;
    }
    if($result) { $this->flush($file); }

    return $result;
  }

}
