<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class CentinelaService
{
  private $name = 'centinela';
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
   * El esqueleto para una pieza nueva
   */
  public function getSchemaInit(array $rutaPieza): array
  {
    return [
      'stt' => [
        'e' => $rutaPieza['est'],
        's' => $rutaPieza['stt']
      ]
    ];
  }

  /**
   * El esqueleto para una pieza que ha sido enviada a cotizar
   * $idCot es el proveedor a quien se le envio, s = status | r = respuesta
   */
  public function getSchemaPiezaCot(string $idCot, array $stt): array
  {
    return [
      $idCot => [
        's' => $stt['est']
      ]
    ];
  }

  /**
   * El esqueleto para una orden nueva usada para el elemento -ord- dentro del centinela
   * el cual indican los status de las ordenes en si.
   */
  public function getSchemaOrden(array $data): array
  {
    return [
      'e' => $data['est'],
      's' => $data['stt']
    ];
  }

  /** */
  public function getContent(): array
  {
    $ordenes = [];
    $path = $this->params->get($this->name);
    $lock = $this->lock->createLock($this->name);
    if ($lock->acquire(true)) {
      if($this->filesystem->exists($path)) {
        $ordenes = json_decode( file_get_contents($path), true );
        if($ordenes == null) {
          $ordenes = [];
        }
      }
    }
    $lock->release();
    return $ordenes;
  }
  
  /** */
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
  public function downloadCentinela(): array
  {
    return $this->getContent();
  }

  /**
   * @see [Cotiza] GetController::enviarOrden
  */
  public function setNewOrden(array $data): bool
  {
    $file = $this->getContent();
    $result = false;
    $addToNon = false;
    if(array_key_exists('ordenes', $file)) {
      $has = in_array($data['idOrden'], $file['ordenes']);
      if($has === false) {
        $file['ordenes'][] = $data['idOrden'];
        $addToNon = true;
        $result = true;
      }
    }else{
      $file['ordenes'][] = $data['idOrden'];
      $addToNon = true;
      $result = true;
    }

    if($addToNon) {
      //--  Colocamos la nueva orden en la sección de no asignadas --
      $file['non'][] = $data['idOrden'];
    }

    if(!array_key_exists('piezas', $file)) {
      $file['piezas'] = [];
    }

    $addPiezas = true;
    if(array_key_exists($data['idOrden'], $file['piezas'])) {

      $rota = count($file['piezas'][$data['idOrden']]);
      if($rota > 0) {
        $addPiezas = false;
        $rota = count($data['piezas']);
        for ($i=0; $i < $rota; $i++) {
          if(!in_array( $data['piezas'][$i], $file['piezas'][$data['idOrden']] )) {
            $file['piezas'][$data['idOrden']][] = $data['piezas'][$i];
            $result = true;
          }
        }
      }
    }
    if($addPiezas) {
      $file['piezas'][$data['idOrden']] = $data['piezas'];
      $result = true;
    }

    $rota = count($file['piezas'][$data['idOrden']]);
    for ($i=0; $i < $rota; $i++) {
      $file['stt'][$data['piezas'][$i]] = $data['stt'];
    }

    if($result) {
      $file['version'] = $data['version'];
      $this->flush($file);
    }

    return $result;
  }

  /**
   * @see [SCP] CentinelaController::enviarOrden
  */
  public function asignarOrdenes(array $asignaciones)
  {
    $file = $this->getContent();
    if(array_key_exists('version', $file)) {

      if(array_key_exists('avo', $file)) {
        foreach ($asignaciones['info'] as $idAvo => $ords) {
          $file['avo'][$idAvo] = $ords;
        }
      }else{
        $file['avo'] = $asignaciones['info'];
      }
      foreach ($asignaciones['info'] as $idAvo => $ords) {
        $file['non'] = array_diff($file['non'], $ords);
      }

      $file = $this->updateVersion(
        $asignaciones['version'],
        $file
      );
      $this->flush($file);
    }
  }

  /** */
  public function setNewSttToOrden(array $data): bool
  {
    $file = $this->getContent();
    $result = false;
    if(array_key_exists('version', $file)) {
      if(!array_key_exists('ord', $file)) {
        $file['ord'] = [];
      }
      $file['ord'][$data['orden']] = $this->getSchemaOrden($data);
      if($data['version'] != 0) {
        $file['version'] = $data['version'];
      }
      $this->flush($file);
      $result = true;
    }
    return $result;
  }

  /** */
  public function setNewSttToPiezas(array $data): bool
  {
    $file = $this->getContent();
    $result = false;
    if(array_key_exists('version', $file)) {
      $ord = $data['orden'];
      if(array_key_exists($ord, $file['piezas'])) {

        $rota = count($file['piezas'][$ord]);
        for ($i=0; $i < $rota; $i++) {
          $pza = (string) $file['piezas'][$ord][$i];
          if(array_key_exists($pza, $file['stt'])) {
            $file['stt'][$pza]['e'] = $data['est'];
            $file['stt'][$pza]['s'] = $data['stt'];
          }
        }
      }
      if($data['version'] != 0) {
        $file['version'] = $data['version'];
      }
      $this->flush($file);
      $result = true;
    }
    return $result;
  }

  /**
   * Checamos la version del centinela para ver si hay cambios
  */
  public function isSameVersion(string $oldVersion): bool
  {
    $dataMain = $this->getContent();
    $result = false;
    if(array_key_exists('version', $dataMain)) {
      if($dataMain['version'] == $oldVersion) {
        $result = true;
      }
    }
    return $result;
  }

  /**
   * Checamos la version del centinela para ver si hay cambios
  */
  public function isSameVersionAndGetVersionNew(string $oldVersion): array
  {
    $dataMain = $this->getContent();
    $result['isSame'] = false;
    $result['newver'] = $oldVersion;
    if(array_key_exists('version', $dataMain)) {
      if($dataMain['version'] == $oldVersion) {
        $result['isSame'] = true;
      }
      $result['newver'] = $dataMain['version'];
    }
    return $result;
  }

  ///
  private function updateVersion(int $version, array $centinela): array
  {
    $centinela['version'] = $version;
    return $centinela;
  }

  /**
   * @see GetFileController::getAllOrdenesByIdAvo
  */
  public function buildMiniFileCentinela(string $avo): array
  {
    $file = $this->getContent();
    $mini = [];
    if(array_key_exists('avo', $file)) {
      if(array_key_exists($avo, $file['avo'])) {

        $ordenes = $file['avo'][$avo];
        $rota = count($ordenes);
        if($rota > 0) {

          $mini = ['version' => $file['version'], 'ordenes' => $file['avo'][$avo]];

          $idsP = [];
          // Las piezas y estatus de las ordenes
          for ($i=0; $i < $rota; $i++) {

            if(array_key_exists($ordenes[$i], $file['piezas'])) {
              $mini['piezas'][ $ordenes[$i] ] = $file['piezas'][ $ordenes[$i] ];
              $idsP = array_merge($idsP, $file['piezas'][ $ordenes[$i] ]);
            }

            if(array_key_exists($ordenes[$i], $file['ord'])) {
              $mini['ord'][ $ordenes[$i] ] = $file['ord'][ $ordenes[$i] ];
            }
          }

          // Los Status
          $rota = count($idsP);
          for ($i=0; $i < $rota; $i++) {
            if(array_key_exists($idsP[$i], $file['stt'])) {
              $mini['stt'][ $idsP[$i] ] = $file['stt'][ $idsP[$i] ];
            }
          }
        }
      }
    }

    return $mini;
  }

}
