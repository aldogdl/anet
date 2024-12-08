<?php

namespace App\Service\ItemTrack;

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
    public function existeInCooler(String $waId, String $idItem)
    {    
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
     * [V6]
     * Borramos archivo de inicio de secion del cotizador o todos en caso
     * de que $waIdCot sea con valor all
    */
    public function deleteInitLoginFile(String $waIdCot): int
    {
        $borrados = 0;
        if($waIdCot == 'all') {
            $public = $this->params->get('phtml');
            $finder = new Finder();
            $finder->files()->in($public)->name('*_iniLogin.json');
            if ($finder->hasResults()) {
                foreach ($finder as $file) {
                    try {
                        $this->delete($file->getPath());
                        $borrados = $borrados + 1;
                    } catch (void) {
                        $borrados = -1;
                    }
                }
            }
        }else{
            try {
                $this->delete('/', $waIdCot."_iniLogin.json");
                $borrados = 1;
            } catch (void) {
                $borrados = -1;
            }
        }

        return $borrados;
    }

    /** 
     * Borramos del Cooler el item del cotizador que esta queriendo cotizar y lo enviamos
     * a tracking para indicar que este cotizador esta cotizando.
    */
    public function putCotizando(WaMsgDto $waMsg): bool
    {    
        $cooler = $this->getContent('coolers', $waMsg->from . '.json');

        if(count($cooler) > 0) {
            $has = array_search($waMsg->idAnet, array_column($cooler, 'idAnet'));
            if($has !== false) {

                try {
                    $item = $cooler[$has];
                    unset($cooler[$has]);
                    $cooler = array_values($cooler);
                    $this->setContent('coolers', $waMsg->from.'.json', $cooler);
                } catch (\Throwable $th) {
                    return false;
                }

                if(array_key_exists('idAnet', $item)) {
                    $date = new \DateTime('now');
                    $item['wamid']   = $waMsg->id;
                    $item['current'] = 'sfto';
                    $item['attend']  = $date->format('Y-m-d h:i:s');
                    $this->setContent('tracking', $waMsg->from.'.json', $item);
                    return true;
                }
            }
        }
        return false;
    }
    
    /** 
     * Revisamos si el cotizador tiene un item actualmente cotizando
    */
    public function hasCotizando(WaMsgDto $waMsg): bool
    {    
        $cotizando = $this->getContent('tracking', $waMsg->from . '.json');
        return (count($cotizando) > 0) ? true : false;
    }

    /** 
     * [V6]
     * Buscamos en el cooler si existe otro bait que podamos enviar de preferencia
     * de la misma marca que el enviado por parametro, si no hay misma marca retornamos
     * la primer opción disponible.
     * @return String El Id del Item encontrado
    */
    public function getNextBait(WaMsgDto $waMsg, String $mrkpref): array
    {
        $mrkpref = mb_strtolower($mrkpref);
        $return = ['idAnet' => 0, 'cant' => 0];

        $cooler = $this->getContent('coolers', $waMsg->from.'.json');
        
        $cantItems = count($cooler);
        if($cantItems == 0) {
            return $return;
        }
        $return['cant'] = $cantItems;
        
        // Si el cotizador dijo [NO TENGO LA MARCA] eliminamos todas los
        // items de esa misma marca.
        if($waMsg->subEvento == 'ntga') {
            for ($i=0; $i < $cantItems; $i++) { 
                if($cooler[$i]['mrk'] == $mrkpref) {
                    unset($cooler[$i]);
                }
            }
            $this->setContent('coolers', $waMsg->from.'.json', $cooler);
        }
        
        if($cantItems > 0) {
            $has = false;
            if($mrkpref != '') {
                $has = array_search($mrkpref, array_column($cooler, 'mrk'));
            }
            // Si no se encontró uno de la misma marca "Si es que el valor de $mrkPref"
            // no esta vacio... tomamos el primero
            $has = ($has === false) ? 0 : $has;
            $return['idAnet'] = $cooler[$has]['idAnet'];
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
