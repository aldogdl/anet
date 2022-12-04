<?php

namespace App\Service;

use DateTime;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

class OpenApp
{
  private $name = 'opcotizo.json';
  private $params;
  private $filesystem;

  public function __construct(ParameterBagInterface $container)
  {
    $this->params = $container;
    $this->filesystem = new Filesystem();
  }

  /** */
  public function getContent(): array
  {
    if($this->filesystem->exists($this->name)) {
      return json_decode(file_get_contents($this->name), true);
    }

    return [];
  }

  /**
   * Guardamos un nuevo registro de apertura de la app cotizo
   * @see 
  */
  public function setNewApertura(String $idUser)
  {

    if(!$this->filesystem->exists($this->name)) {
      file_put_contents($this->name, json_encode([]));
    }
    $partes = explode('::', $idUser);
    if(count($partes) < 2){ return; }
    $user  = $partes[0];
    $where = $partes[1];

    $content = json_decode(file_get_contents($this->name), true);
    $hoy = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));

    $array = ['where' => $where, 'date' => [$hoy->format('Y-m-d H:i:s')]];
    if(array_key_exists($user, $content)) {

        $data = $content[$user];
        $data['last'] = $array;
        if(array_key_exists($where, $data['hist'])) {

          $data['hist'][$where][] = $hoy->format('Y-m-d H:i:s');
          if(count($data['hist'][$where]) > 10) {
            unset($data['hist'][$where][0]);
            sort($data['hist'][$where]);
          }
          
        }else{
          $data['hist'][$where][] = $hoy->format('Y-m-d H:i:s');
        }

        $content[$user] = $data;

    }else{
      $content[$user] = [ 'last' => $array, 'hist' => [$where => [$hoy->format('Y-m-d H:i:s')]] ];
    }
    
    $this->filesystem->dumpFile($this->name, json_encode($content));
  }

}
