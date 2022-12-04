<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FiltrosService
{
  // En esta carpeta se guardan todos los nuevos filtros
  private $nameFolder = 'filtros';
  private $nameNtg = 'filNoTgo';
  private $params;
  private $filesystem;

  public function __construct(ParameterBagInterface $container)
  {
    $this->params = $container;
    $this->filesystem = new Filesystem();
  }

  /** */
  private function schemaNtgo(): array
  {
    // Cotizador que indica no la tengo
    // Es un array que como clave es la orden y sus valores son arrays de:
    // clave (idCotizador) y su valor un array de ids de Piezas

    // Eje. {
    //   "5":{
    //       "2":[1,2,3],
    //       "12":[1,2,3],
    //       "22":[1,2,3]
    //   },
    //   "6":{
    //       "2":[4,5,6],
    //       "12":[4,5,6],
    //       "22":[4,5,6]
    //   }
    // }
    
    return [];
  }

  /**
   * Recuperamos todos los archivos
  */
  public function getAllNoTengo(bool $delete = true): array
  {
    $files = [];
    $path = Path::normalize($this->params->get($this->nameFolder));
    
    if($this->filesystem->exists($path)) {
      $finder = new Finder();
      $finder->files()->in($path)->name('*.ntg')->sortByName();
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

  /** */
  private function getContent(): array
  {
    $filtros = [];
    $fileMain = $this->params->get($this->nameNtg);
    if($this->filesystem->exists($fileMain)) {
      $filtros = json_decode( file_get_contents($fileMain), true );
    }    
    return $filtros;
  }

  /** */
  public function downloadFiltros(): array
  {
    return $this->getContent();
  }

  /** */
  public function setNewRegNoTengo(String $filename)
  {
    $path = $this->params->get($this->nameFolder);
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
    }
    $path = Path::normalize($this->params->get($this->nameFolder) .'/'. $filename);
    file_put_contents($path, '');
  }

  /** 
   * Cada ves que harbi detecte que son 10 o mas registros, se guardará
   * en el archivo oficial de filtros.
  */
  public function setTheRegsInFileNoTengo(): bool
  {
    $fileMain = $this->params->get($this->nameNtg);
    $hasChanges = false;
    $content = [];

    // Primeramente recogemos todos los datos de los archivos registro
    $archivos = $this->getAllNoTengo(false);
    $rota = count($archivos);
    if($rota > 0) {

      $hasChanges = true;
      if($this->filesystem->exists($fileMain)) {
        $content = json_decode( file_get_contents($fileMain), true );
      }
 
      for ($i=0; $i < $rota; $i++) {
        // apr__1-6-2pp1__1669432499307
        // Partimos el nombre del archivo por su sep -
        $garbash = explode('__', $archivos[$i]);
        $ids = $garbash[1];
        if(strpos($ids, 'pp') !== false) {
          $ids = str_replace('pp', '-', $ids);
        }
        $partes = explode('-', $ids);
        // El resultado del archivo es:
        // [0] = El id de la orden
        // [1] = El id del cotizador
        // [2] = El id del AVO --> El cual es eliminado
        // [3] = El id de la pieza que no tiene
        unset($partes[2]);
        $partes = array_values($partes);

        $has = count($partes);
        if($has > 0) {

          // Revisamos si la orden ya existe entre la primera key
          if(array_key_exists($partes[0], $content)) {
            if(array_key_exists($partes[1], $content[$partes[0]])) {
              if(!in_array($partes[2], $content[$partes[0]][$partes[1]])) {
                $content[$partes[0]][$partes[1]][] = $partes[2];
              }
            }else{
              // No existe el cotizador en el archivo de registro
              $content[$partes[0]][$partes[1]] = [$partes[2]];
            }
          }else{
            // No existe la orden en el archivo de registri
            $content[$partes[0]] = [$partes[1] => [$partes[2]]];
          }
        }
      }

      file_put_contents($fileMain, json_encode($content));
    }
    
    return $hasChanges;
  }

  /**
   * Recuperamos todas las piezas del cotizador que nos ha indicado que no la tiene
   * @return array Los ids de las piezas que no tiene.
  */
  public function getMyAllNtnByidCot(int $idCot): array
  {
    $items = [];
    $finded = [];
    $path = $this->params->get($this->nameNtg);

    if($this->filesystem->exists($path)) {
      $items = json_decode( file_get_contents($path), true );

      foreach ($items as $idOrden => $cotz) {
        if(array_key_exists(''.$idCot, $items[$idOrden])) {
          $finded[$idOrden] = $items[$idOrden][$idCot];
        }
      }
    }
    return $finded;
  }

  /**
   * Recuperamos todas las piezas del cotizador que nos ha indicado que no la tiene
   * @return array Los ids de las piezas que no tiene.
  */
  public function getNtnByidCot(int $idCot): array
  {
    $items = [];
    $finded = [];
    $ordenes = [];
    $piezas = [];
    $path = $this->params->get($this->nameNtg);

    if($this->filesystem->exists($path)) {
      $items = json_decode( file_get_contents($path), true );

      foreach ($items as $idOrden => $cotz) {
        if(array_key_exists(''.$idCot, $items[$idOrden])) {
          $finded[$idOrden] = $items[$idOrden][$idCot];
          $piezas = array_merge($piezas, $items[$idOrden][$idCot]);
          $ordenes[] = $idOrden;
        }
      }
    }
    sort($piezas);
    sort($ordenes);
    return [ 'array' => $finded, 'pzas' => $piezas, 'ords' => $ordenes ];
  }

}
