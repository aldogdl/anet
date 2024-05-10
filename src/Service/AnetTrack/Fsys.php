<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Fsys {

    private Filesystem $filesystem;
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $container)
    {
        $this->params = $container;
        $this->filesystem= new Filesystem();
    }
    
    /** */
    public function getContent(String $folder, String $filename = ''): array | String
    {
        $path = $this->getFolderTo($folder);
        $tipoReturn = 'string';
        if( mb_strpos($filename, '.json')) {
            $tipoReturn = 'map';
        }
        if($filename != '') {
            $path = $path . '/' .$filename;
        }

        try {
            $content = file_get_contents($path);
            if($content != '' && $tipoReturn == 'map') {
                return json_decode($content, true);
            }
		} catch (FileException $e) {}

        return $content;
    }

    /** */
    public function setContent(String $folder, String $filename = '', array $content): void
    {
        $path = $this->getFolderTo($folder);
        if($filename != '') {
            $path = $path . '/' .$filename;
        }

        try {
			$this->filesystem->dumpFile($path, json_encode($content));
		} catch (FileException $e) {}
    }

    /** */
    public function delete(String $folder, String $filename = ''): void
    {
        $path = $this->getFolderTo($folder);
        if($filename != '') {
            $path = $path . '/' .$filename;
        }
        try {
            if(is_file($path)) {
                $this->filesystem->remove($path);
            }
		} catch (FileException $e) {}
    }

    /** */
    public function existe(String $folder, String $filename = ''): bool
    {
        $path = $this->getFolderTo($folder);
        if($filename != '') {
            $path = $path . '/' .$filename;
        }
        return $this->filesystem->exists($path);
    }

    /** */
    public function getConmuta(): array
    {
        $path = $this->params->get('tkwaconm');
        try {
            $content = file_get_contents($path);
            if($content != '') {
                return json_decode($content, true);
            }
        } catch (FileException $e) {}

        return [];
    }

    /** 
     * Borramos del Cooler el bait del cotizador que esta queriendo cotizar y lo enviamos
     * a tracking para indicar que este cotizador esta cotizando un Item 
    */
    public function putCotizando(WaMsgDto $waMsg): bool
    {    
        $est = $this->getContent('waEstanque', $waMsg->from . '.json');
        if(count($est) > 0) {
            if(array_key_exists('items', $est)) {

                $cooler = $est['items'];
                if(count($cooler) > 0) {
                    $idsItems = array_column($cooler, 'idItem');
                    $has = array_search($waMsg->idItem, $idsItems);
                    if($has !== false) {
                        
                        $bait = $cooler[$has];
                        $date = new \DateTime('now');
                        $attend = $date->format('Y-m-d h:i:s');
                        $bait['wamid'] = $waMsg->id;
                        $bait['current'] = 'sfto';
                        $bait['attend'] = $attend;

                        unset($cooler[$has]);
                        $est['items'] = $cooler;
                        $this->setContent('tracking', $waMsg->from . '.json', $bait);
                        $this->setContent('waEstanque', $waMsg->from . '.json', $est);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** 
     * Buscamos entre el estanque si existe otro bait que podamos enviar de preferencia
     * del mismo modelo que el enviado por parametro, si no hay mismo modelo retornamos
     * la primer opcion disponible.
     * @return String El Id del Item encontrado
    */
    public function getNextBait(WaMsgDto $waMsg, String $mdlpref = ''): String
    {    
        $est = $this->getContent('waEstanque', $waMsg->from . '.json');
        if(count($est) > 0) {
            if(array_key_exists('items', $est)) {

                $cooler = $est['items'];
                if(count($cooler) > 0) {
                    $has = 0;
                    if($mdlpref != '') {
                        $mdls = array_column($cooler, 'mdl');
                        $has = array_search($mdlpref, $mdls);
                    }
                    if($has !== false) {
                        return $cooler[$has]['idItem'];
                    }
                }
            }
        }
        return '';
    }

    /** 
     * Uso interno para contruir el folder de destino
    */
    private function getFolderTo(String $folder): String
    {
        $folder = ($folder == '/') ? 'phtml' : $folder;
        $path = Path::canonicalize($this->params->get($folder));
        if(!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        return $path;
    }
    
}
