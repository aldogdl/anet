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
        $this->filesystem = new Filesystem();
    }
    
    /** */
    public function existeInCooler(String $waId, String $idItem) {
        
        $cooler = $this->getContent('waEstanque', $waId.'.json');

        if(count($cooler) > 0) {
            if(array_key_exists('baits', $cooler)) {
                $baits = array_column($cooler['baits'], 'idItem');
                if(count($baits) > 0) {
                    $has = array_search($idItem, $baits);
                    if($has !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /** */
    public function setStopStt(String $waIdCot): void
    {
        try {
			$this->filesystem->dumpFile($waIdCot.'_stopstt.json', '');
		} catch (FileException $e) {}
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
            if(array_key_exists('baits', $cooler)) {

                $baits = $cooler['baits'];
                if(count($baits) > 0) {

                    $has = array_search($waMsg->idItem, array_column($baits, 'idItem'));
                    if($has !== false) {

                        try {
                            $bait = $baits[$has];
                            unset($baits[$has]);
                            $cooler['baits'] = array_values($baits);
                            $this->setContent('waEstanque', $waMsg->from.'.json', $cooler);
                        } catch (\Throwable $th) {
                            return false;
                        }

                        if(array_key_exists('idItem', $bait)) {
                            $date = new \DateTime('now');
                            $bait['wamid']   = $waMsg->id;
                            $bait['current'] = 'sfto';
                            $bait['attend']  = $date->format('Y-m-d h:i:s');
                            $this->setContent('tracking', $waMsg->from.'.json', $bait);
                            return true;
                        }
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
    public function getNextBait(WaMsgDto $waMsg, String $mrkpref): array
    {    
        $return = ['send' => '', 'baitsInCooler' => []];

        $est = $this->getContent('waEstanque', $waMsg->from.'.json');
        if(count($est) > 0) {
            if(array_key_exists('baits', $est)) {

                if($waMsg->subEvento == 'ntga') {
                    $rota = count($est['baits']);
                    // Si el cotizador dijo no tengo la marca eliminamos todas las
                    // autopartes de esa misma marca.
                    for ($i=0; $i < $rota; $i++) { 
                        if($est['baits'][$i]['mrk'] == $mrkpref) {
                            unset($est['baits'][$i]);
                        }
                    }
                    $this->setContent('waEstanque', $waMsg->from.'.json', $est);
                }

                $baits = $est['baits'];
                $return['baitsInCooler'] = count($baits);
                if($return['baitsInCooler'] > 0) {
                    $has = array_search($mrkpref, array_column($baits, 'mrk'));
                    $has = ($has === false) ? 0 : $has;
                    $return['send'] = $baits[$has]['idItem'];
                }
            }
        }

        return $return;
    }

    /** 
     * Hacemos un resumen de todos los baits con los que cuenta el cotizador
     * @return array La lista de los IdItems
    */
    public function getResumeCooler(String $waId): array
    {    
        $return = [];
        $est = $this->getContent('waEstanque', $waId . '.json');
        if(count($est) > 0) {
            if(array_key_exists('items', $est)) {

                $baits = $est['items'];
                if(count($baits) > 0) {
                    $return = array_column($baits, 'idItem');
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
    public function getFolderTo(String $folder): String
    {
        $folder = ($folder == '/') ? 'phtml' : $folder;
        $path = Path::canonicalize($this->params->get($folder));
        if(!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        return $path;
    }
    

}
