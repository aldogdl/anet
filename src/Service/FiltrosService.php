<?php

namespace App\Service;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FiltrosService
{
  // En esta carpeta se guardan todos los nuevos filtros
  private $nameFolder = 'filtros';
  // Este es el archivo que almacena todos los filtros
  private $nameFile = 'filtrosF';
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
  private function schema(): array
  {
    // cnt = Cotizador que indica no ta tengo
    // Es un array que como clave es la orden y sus valores son arrays de:
    // clave (idCotizador) = valor (idPieza) "No tengo (esta) pieza de la orden (esta)"

    // cnm = Cotizador que indica que no maneja la Pieza(p), El Año(a), El Modelo(m) o Marca(k) 
    // Es un array que como clave es el cotizador y sus valores son arrays de:
    // clave (elPorQue_NoManeja) = valor (RespectivoId) "No tengo (esta) pieza de la orden (esta)"

    // Eje. [
    //   'cnt' => [
    //     '1' => [
    //       '5' => '1',
    //     ]
    //   ],
    //   'cnm' => [
    //     '5' => ['p' => '1', 'a' => '1975', 'm' => '1', 'k' => '1']
    //   ]
    // ];

    return [ 'cnt' => [], 'cnm' => [] ];
  }

  /** */
  private function init()
  {
    $pathLock = $this->params->get('filtrosF');
    $store = new FlockStore($pathLock);
    $this->lock = new LockFactory($store);
  }

  /**
   *
  */
  public function getContent(bool $clean = false): array
  {
    $items = [];
    $path = $this->params->get($this->nameFile);
    $lock = $this->lock->createLock($this->nameFile);
    $makeFlush = false;
    if ($lock->acquire(true)) {
      if($this->filesystem->exists($path)) {
        $items = json_decode( file_get_contents($path), true );
        if($items == null) {
          $items = $this->schema();
          $makeFlush = true;
        }
      }else{
        $this->filesystem->mkdir($path);
        $makeFlush = true;
        $items = $this->schema();
      }
    }
    if($makeFlush) {
      $this->flush($items);
    }

    $lock->release();
    return $items;
  }

  /** */
  public function flush(array $newContent)
  {
    $path = $this->params->get($this->nameFile);
    $lock = $this->lock->createLock($this->nameFile);
    if ($lock->acquire(true)) {
      $this->filesystem->dumpFile($path, json_encode($newContent));
    }
    $lock->release();
  }

  /**
   * Revisamos si hay archivoa que indican que un cotizador creo un nuevo filtro
  */
  public function hasFiltrosOf(String $ext): bool
  {
    $path = Path::normalize($this->params->get($this->nameFolder));
    if(!$this->filesystem->exists($path)) {
      
      $this->filesystem->mkdir($path);
      return false;
    }else{

      $finder = new Finder();
      $finder->files()->in($path)->name('*.'.$ext);
      if ($finder->hasResults()) {
        return true;
      }
    }
    return false;
  }

  /** */
  public function setNewFiltro(int $filtro)
  {
    $path = Path::normalize($this->params->get($this->nameFolder) .'/'. $filtro);
    file_put_contents($path, '');
  }

}
