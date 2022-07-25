<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

class OrdenesAsigns
{
  private $name = 'ordAsigns';
  private $params;
  private $filesystem;

  public function __construct(ParameterBagInterface $container)
  {
    $this->params = $container;
    $this->filesystem = new Filesystem();
  }

  /** */
  public function hasContent(): bool
  {
    $base = $this->params->get($this->name);
    if($this->filesystem->exists($base)) {

      $finder = new Finder();
      $finder->files()->in($base)->name('*.txt');
      if ($finder->hasResults()) {
        foreach ($finder as $file) {
          $this->filesystem->remove($file->getRealPath());
        }
        return true;
      }
    }
    return false;
  }
  
  /**
   * @see 
  */
  public function setNewOrdAsigns(String $centinelaVer)
  {
    $base = $this->params->get($this->name);
    if(!$this->filesystem->exists($base)) {
      $this->filesystem->mkdir($base);
    }

    $path = $base . '/' .$centinelaVer .'.txt';
    $this->filesystem->dumpFile($path, '');
  }

}
