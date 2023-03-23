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
    $folder = $this->params->get($this->scm);
    $path = Path::normalize($folder);
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }

    $filename = $camp['created'].'.json';
    $path = Path::normalize($folder.'/'.$filename);

    if($this->filesystem->exists($path)) {
      $sufix = $this->findConsecutivo();
      $filename = $camp['created'].'_'.$sufix.'.json';
      $path = Path::normalize($folder.'/'.$filename);
    }
    
    $camp['filename'] = $filename;
    file_put_contents($path, json_encode($camp));
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
    return ($finder->hasResults()) ? true : false;
  }

  /**
   * Revisamos si hay archivoa que indican que un receiver ya
   * realizo alguna actividad en la app
  */
  public function hasRegsAny(): bool
  {
    $path = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }
    $finder = new Finder();
    $finder->files()->in($path)->notName(['*.rsp', '*.json']);
    return ($finder->hasResults()) ? true : false;
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
   * Recuperamos todas las campañas existentes
  */
  public function getAllCampings(): array
  {
    $files = [];
    $path = Path::normalize($this->params->get($this->scm));
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
      return [];
    }
    $finder = new Finder();
    $finder->files()->in($path)->name('*.json')->sortByAccessedTime();
    if ($finder->hasResults()) {
      foreach ($finder as $file) {
        $files[] = json_decode($file->getContents());
        $this->filesystem->remove($file->getRealPath());
      }
    }
    return $files;
  }

  /**
   * Recuperamos todos los archivos
  */
  public function getAll(bool $delete = true): array
  {
    $files = [];
    $path = Path::normalize($this->params->get($this->scm));
    if($this->filesystem->exists($path)) {
      $finder = new Finder();
      $finder->files()->in($path)->notName('*.rsp')->sortByName();
      if ($finder->hasResults()) {
        foreach ($finder as $file) {
          $files[] = $file->getFilenameWithoutExtension();
          if($delete) {
            $this->filesystem->remove($file->getRealPath());
          }
        }
      }
    }
    return $files;
  }

  /**
   * Recuperamos todos los archivos que indican:
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
      return [];
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

  /**
   * Desmenusamos el link de cualquier archivo enviado por parametro
  */
  public function spellLink(String $filename): array
  {
    $partes = explode('-', $filename);
    $rota = count($partes);
    for ($l=0; $l < $rota; $l++) { 
      if(strpos($partes[$l], '.')) {
        $sub = explode('.', $partes[$l]);
        $partes[$l] = $sub[0];
        $partes[] = $sub[1];
      }
    }
    $rota = count($partes);
    if($partes[$rota-1] == 'see') {
      return [
        'orden' => $partes[0],
        'user'  => $partes[1],
        'avo'   => $partes[2],
        'from'  => $partes[3],
        'time'  => $partes[4],
        'type'  => $partes[5],
      ];
    }
    return $partes;
  }

}
