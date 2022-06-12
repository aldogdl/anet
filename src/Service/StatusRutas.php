<?php

namespace App\Service;

use App\Entity\Ordenes;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Finder\Finder;
use function Symfony\Component\String\s;

class StatusRutas
{

  private $params;
  private $filesystem;

  public function __construct(ParameterBagInterface $container)
  {
    $this->params = $container;
    $this->filesystem = new Filesystem();
  }

  /** */
  public function setNewRuta(array $data)
  {
    $pathTo = $this->params->get('rutas');
    file_put_contents($pathTo, json_encode($data));
  }

  /**
   * Obtenemos el ultimo archivo creado para las rutas.
   */
  public function getAllRutas(): array
  {
    $ruta = $this->params->get('datafix');
    $filename = 'rutas.json';
    if($this->filesystem->exists($ruta.$filename)) {
      return json_decode( file_get_contents($ruta.$filename), true );
    }
    return [];
  }

  /**
   * Obtenemos el nombre del ultimo archivo creado para las rutas.
   */
  public function getLastRutaName(): string
  {
      $ruta = $this->params->get('datafix');
      $finder = new Finder();
      $finder->files()->in($ruta)->name('*.last');
      if ($finder->hasResults()) {
          foreach ($finder as $file) {
              $lastRuta = $file->getRelativePathname();
          }
      }
      $ver = '0';
      if(strlen($lastRuta) > 0) {
          $ver = str_replace('.last', '', $lastRuta);
      }
      return trim($ver);
  }

  /**
   * Obtenemos el archivo solicitado de las rutas
   * ------ hasta ahorita marcado para borrarce -------------
   */
  public function getRutaByFilename(String $versionRuta): array
  {
      $ruta = $this->params->get('datafix');

      if($this->filesystem->exists($ruta.$versionRuta.'.json')) {
          return json_decode( file_get_contents($ruta.$versionRuta.'.json'), true );
      }
      return [];
  }

  /**
   * Buscamos la siguiente estacion de la orden y el primer status de esta.
   */
  public function getEstNextSttFirstByOrden(Ordenes $orden, array $rutas): array
  {
      $estInt = (int) $orden->getEst();
      $estInt = $estInt + 1;
      $estInt = (string) $estInt;

      if(array_key_exists($estInt, $rutas)) {
          return [
              'est' => $estInt,
              'stt' => $rutas[$estInt][0]
          ];
      }
      return [
          'est' => $orden->getEst(),
          'stt' => $orden->getStt()
      ];
  }

  /**
   * Buscamos la estacion inicial de la orden con piezas
   */
  public function getEstOrdenConPiezas(array $rutas): array
  {
    $estInt = '0';
    foreach ($rutas['est'] as $key => $value) {
      $newStr = s($value)->lower();
      if($newStr->startsWith('registrando')){
        $estInt = (string) $key;
        break;
      }
    }

    $sttInt = '0';
    if($estInt != '0') {
      foreach ($rutas['stt'][$estInt] as $key => $value) {
        $newStr = s($value)->lower();
        if($newStr->equalsTo('enviar orden')){
          $sttInt = (string) $key;
          break;
        }
      }
    }

    if($estInt != '0' && $sttInt != '0') {
      return [
        'est' => (string) $estInt,
        'stt' => (string) $sttInt
      ];
    }

    return [
      'est' => '1',
      'stt' => '2'
    ];
  }

  /**
   * Buscamos la estacion inicial de la orden sin piezas
   */
  public function getEstOrdenSinPiezas(array $rutas): array
  {
    $estInt = '0';
    foreach ($rutas['est'] as $key => $value) {
      $newStr = s($value)->lower();
      if($newStr->startsWith('registrando')){
        $estInt = $key;
        break;
      }
    }
    $sttInt = '0';
    if($estInt != '0') {
      foreach ($rutas['stt'][$estInt] as $key => $value) {
        $newStr = s($value)->lower();
        if($newStr->equalsTo('regristrar piezas')){
          $sttInt = $key;
          break;
        }
      }
    }

    if($estInt != '0' && $sttInt != '0') {
      return [
        'est' => (string) $estInt,
        'stt' => (string) $sttInt
      ];
    }
    return [
      'est' => '1',
      'stt' => '1'
    ];
  }

  /**
   * Buscamos la siguiente estacion de la orden y el primer status de esta.
   */
  public function getEstInitDeProcesosByOrden(array $status): array
  {
    $estInt = '0';
    foreach ($status['est'] as $key => $value) {
      $newStr = s($value)->lower();
      if($newStr->equalsTo('orden en procesamiento')){
        $estInt = $key;
        break;
      }
    }

    if($estInt != '0') {
      return [
        'est' => (string) $estInt,
        'stt' => '1'
      ];
    }
    return [
      'est' => '2',
      'stt' => '1'
    ];
  }

  /**
   * Buscamos la primera estacion y su estatus que corresponda a buscando piezas
   */
  public function getEstInitDeLasPiezas(array $status): array
  {
    $estInt = '0';
    foreach ($status['est'] as $key => $value) {
      $newStr = s($value)->lower();
      if($newStr->containsAny('procesamiento')){
        $estInt = $key;
        break;
      }
    }

    if($estInt != '0') {
      return [
        'est' => (string) $estInt,
        'stt' => '1'
      ];
    }
    return [
      'est' => '2',
      'stt' => '1'
    ];
  }

}
