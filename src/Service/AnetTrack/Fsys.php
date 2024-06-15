<?php

namespace App\Service\AnetTrack;

use Symfony\Component\Finder\Finder;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Dtos\WaMsgDto;

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
        $content = '';

        if(mb_strpos($filename, '.json') !== false) {
            $tipoReturn = 'map';
            $content = [];
        }
        if($filename != '') {
            $path = $path . '/' .$filename;
        }

        try {
            if($this->existe($folder, $filename)) {
                $content = file_get_contents($path);
                if($content != '' && $tipoReturn == 'map') {
                    return json_decode($content, true);
                }
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
        $cooler = $this->getContent('waEstanque', $waMsg->from . '.json');

        if(count($cooler) > 0) {
            if(array_key_exists('items', $cooler)) {

                $baits = $cooler['items'];
                if(count($baits) > 0) {

                    $idsItems = array_column($baits, 'idItem');
                    $has = array_search($waMsg->idItem, $idsItems);
                    if($has !== false) {
                        
                        $bait = $baits[$has];
                        $date = new \DateTime('now');
                        $attend = $date->format('Y-m-d h:i:s');
                        $bait['wamid'] = $waMsg->id;
                        $bait['current'] = 'sfto';
                        $bait['attend'] = $attend;

                        unset($baits[$has]);
                        $cooler['items'] = array_values($baits);
                        $this->setContent('/', $waMsg->from."_stopstt.json", ['']);
                        $this->setContent('tracking', $waMsg->from.'.json', $bait);
                        $this->setContent('waEstanque', $waMsg->from.'.json', $cooler);
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
    public function getNextBait(WaMsgDto $waMsg, String $mdlpref = ''): array
    {    
        $return = ['send' => '', 'baitsInCooler' => []];

        $est = $this->getContent('waEstanque', $waMsg->from . '.json');
        if(count($est) > 0) {
            if(array_key_exists('items', $est)) {

                $baits = $est['items'];
                if(count($baits) > 0) {

                    $return['baitsInCooler'] = array_column($baits, 'idItem');
                    $has = 0;
                    if($mdlpref != '') {
                        $mdls = array_column($baits, 'mdl');
                        $has = array_search($mdlpref, $mdls);
                        $has = ($has === false) ? 0 : $has;
                    }
                    if($has !== false) {
                        $return['send'] = $baits[$has]['idItem'];
                    }
                }
            }
        }

        return $return;
    }

    /** 
     * Buscamos y retornamos un archivo dentro de la carpeta indicada por parametro
     * donde el archivo comience con...
    */
    public function startWith(String $filename, String $folder = 'phtml'): String
    {
        $public = $this->params->get($folder);
        $finder = new Finder();
		$finder->files()->in($public)->name($filename.'*');
		if ($finder->hasResults()) {
			$files = [];
			foreach ($finder as $file) {
				return $file->getRelativePathname();
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
