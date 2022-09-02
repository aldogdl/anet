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
  // Este es el archivo que almacena todos los filtros
  private $nameFile = 'filtrosF';
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
   * Buscamos si algun cotizador ha dicho no la tengo
   *@return La cantidad de registros encontrados
  */
  public function hasRegNoTng(): int
  {
    $path = $this->params->get($this->nameFolder);
    if(!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path);
      return 0;
    }else{
      $finder = new Finder();
      $finder->files()->in($path)->name('*.ntg');
      return $finder->count();
    }
  }

  /** 
   * Cada ves que harbi detecte que son 10 o mas registros, se guardará
   * en el archivo oficial de filtros.
  */
  public function setTheRegsInFileNoTengo()
  {
    $path = $this->params->get($this->nameFolder);
    $fileMain = $this->params->get($this->nameNtg);

    // Primeramente recogemos todos los datos de los archivos registro
    $archivos = [];
    $finder = new Finder();
    $finder->files()->in($path)->name('*.ntg')->sortByName();
    if ($finder->hasResults()) {
      foreach ($finder as $file) {
        $archivos[] = $file->getFilenameWithoutExtension();
        $this->filesystem->remove($file->getRealPath());
      }
    }

    $content = [];
    $rota = count($archivos);
    if($rota > 0) {

      if($this->filesystem->exists($fileMain)) {
        $content = json_decode( file_get_contents($fileMain), true );
      }
 
      for ($i=0; $i < $rota; $i++) {
        // Corroboramos que no tenga la extencion.
        $file = str_replace('.ntg', '', $archivos[$i]);
        $file = trim($file);
        // Partimos el nombre del archivo por su sep -
        $partes = explode('-', $file);
        // El resultado del archivo es:
        // [0] = El id de la orden
        // [1] = El id del cotizador
        // [2] = El id de la pieza que no tiene
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
    
    return;
  }

  /**
   * El cotizador al iniciar la app de cotizo, esta corroborará si han
   * cambiando los registros de no la tengo, esto con la finalidad de 
   * mantener los menos datos posibles guardados en el dispositivo del usuario
   * @return Los ids de las ordenes que ya no existen en el archivo de registro.
  */
  public function getMyNoTengo(array $lstOrds): array
  {
    $items = [];
    $caducos = [];
    $path = $this->params->get($this->nameNtg);

    if($this->filesystem->exists($path)) {
      $items = json_decode( file_get_contents($path), true );
      $rota = count($lstOrds);
      for ($i=0; $i < $rota; $i++) { 
        if(!array_key_exists($lstOrds[$i], $items)) {
          $caducos[] = $lstOrds[$i];
        }
      }
      return $caducos;
    }else{
      return $lstOrds;
    }
  }

}
