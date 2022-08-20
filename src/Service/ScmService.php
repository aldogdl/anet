<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ScmService
{
  private $name = 'targets';
  private $scm = 'scm';
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
  public function setNewMsg(int $idCamp)
  {
    $file = $this->getContent();
    $result = false;
    $has = in_array($idCamp, $file);
    if($has === false) {
      $file[] = $idCamp;
      $result = true;
    }
    if($result) {
      $this->flush($file);
    }
  }

  /**
   * Revisamos si hay archivoa que indican que un receiver ya descargo y vio
   * la solicitud de cotizacion enviada por link por whatsapp
  */
  public function hasRegsOf(String $ext): bool
  {
    $path = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }
    $finder = new Finder();
    $finder->files()->in($path)->name('*.'.$ext);
    if ($finder->hasResults()) {
      return true;
    }
    return false;
  }

  /**
   * Guardamos un archivo que indica:
   * -> Que un receiver ya descargo y vio
   * -> Que el receiver ya respondio 
   * la solicitud de cotizacion enviada por link por whatsapp.
  */
  public function setNewRegType(String $filename)
  {
    $path = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }
    $this->filesystem->dumpFile($path .'/'.$filename, '');
  }

  /**
   * Recuperamos todos los archivoa que indican:
   * -> Que un receiver ya descargo
   * -> Que un receiver ya respondio 
   * y vio la solicitud de cotizacion enviada por link por whatsapp
  */
  public function getAllRegsOf(String $ext): array
  {
    $files = [];
    $path = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }
    $finder = new Finder();
    $finder->files()->in($path)->name('*.'.$ext)->sortByAccessedTime();
    if ($finder->hasResults()) {
      foreach ($finder as $file) {
        $files[] = $file->getFilenameWithoutExtension();
        $this->filesystem->remove($file->getRealPath());
      }
    }
    return $files;
  }

}
