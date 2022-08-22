<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ScmService
{
  private $scm = 'scm';
  private $params;
  private $filesystem;
  private $lock;

  public function __construct(ParameterBagInterface $container)
  {
    $this->params = $container;
    $this->filesystem = new Filesystem();
  }

  /**
   * @see
  */
  public function setNewMsg(array $camp)
  {
    $folder = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($folder)) {
      $this->filesystem->mkdir($folder);
    }
    $filename = $camp['created'].'.json';
    $path = Path::normalize($folder.'/'.$filename);
    if($this->filesystem->exists($path)) {
      $sufix = $this->findConsecutivo();
      $filename = $camp['created'].'_'.$sufix.'.json';
      $path = Path::normalize($folder.'/'.$filename);
    }
    file_put_contents($filename, json_encode($camp));
  }

  /**
   * En caso de que el nombre del archivo exista, buscamos un sufijo consecutivo
  */
  private function findConsecutivo(): int
  {
    $path = Path::normalize($this->params->get($this->scm));
    $finder = new Finder();
    $finder->files()->in($path);
    return $finder->count() + 1;
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
