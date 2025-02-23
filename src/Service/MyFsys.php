<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Dtos\WaMsgDto;

class MyFsys
{
    private Filesystem $filesystem;
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $contenedor)
    {
        $this->params = $contenedor;
        $this->filesystem = new Filesystem();
    }
    
    /** */
    public function updateFechaLoginTo(String $slug, String $waId): String
    {
        $filename = $slug . '.json';
        $map = $this->getContent('dtaCtc', $filename);
        $result = '';
        if(array_key_exists('colabs', $map)) {
            $colabs = $map['colabs'];
            $has = array_search($waId, array_column($colabs, 'waId'));
            if($has !== false) {
                
                $hoy = new \DateTime('now');
                $result = $hoy->format('Y-m-d\TH:i:s.v');
                $colabs[$has]['login'] = $result;

                $hoy = $hoy->add(new \DateInterval('PT23H55M'));
                $colabs[$has]['kduk'] = $hoy->format('Y-m-d\TH:i:s.v');
                $colabs[$has]['stt'] = 1;

                $map['colabs'] = $colabs;
                $this->setContent('dtaCtc', $filename, $map);
            }
        }
        return $result;
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
                        if(is_file($file->getRealPath())) {
                            $this->filesystem->remove($file->getRealPath());
                            $borrados = $borrados + 1;
                        }
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
     * [V6]
     * Borramos todos los archivos generados al momento de enviar un SSE a AnetTrack
     * y de un determinado item y un determinado cotizador
    */
    public function deleteSendmyFiles(String $idDbSr, String $waIdCot): int
    {
        $borrados = 0;
        $waSendmy = $this->params->get('waSendmy');
        $finder = new Finder();
        $finder->files()->in($waSendmy)->name($idDbSr.'*'.$waIdCot.'.json');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                try {
                    if(is_file($file->getRealPath())) {
                        $this->filesystem->remove($file->getRealPath());
                        $borrados = $borrados + 1;
                    }
                } catch (void) {
                    $borrados = -1;
                }
            }
        }

        return $borrados;
    }

    /** 
     * [V6]
    */
    public function existeInCooler(String $waId, String $idDbSr)
    {    
        $cooler = $this->getContent('coolers', $waId.'.json');

        if(count($cooler) > 0) {
            $items = array_column($cooler, 'idDbSr');
            if(count($items) > 0) {
                $has = array_search($idDbSr, $items);
                if($has !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /** 
     * [V6]
     * Borramos del Cooler el item del cotizador que esta queriendo cotizar y lo enviamos
     * a tracking para indicar que este cotizador esta cotizando.
    */
    public function putCotizando(WaMsgDto $waMsg, bool $withResults = false): bool | array
    {    
        $cooler = $this->getContent('coolers', $waMsg->from . '.json');

        if(count($cooler) > 0) {
            $has = array_search($waMsg->idDbSr, array_column($cooler, 'idDbSr'));
            if($has !== false) {
                try {
                    $item = $cooler[$has];
                    unset($cooler[$has]);
                    $cooler = array_values($cooler);
                    $this->setContent('coolers', $waMsg->from.'.json', $cooler);
                } catch (\Throwable $th) {
                    return ($withResults) ? [] : false;
                }

                if(array_key_exists('idDbSr', $item)) {
                    $date = new \DateTime('now');
                    $item['wamid']   = $waMsg->id;
                    $item['current'] = 'sfto';
                    $item['attend']  = $date->format('Y-m-d h:i:s');
                    $this->setContent('tracking', $waMsg->from.'.json', $item);
                    return ($withResults) ? $item : false;
                }
            }
        }

        return ($withResults) ? [] : false;
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
     * Buscamos en el cooler si existe otro item que podamos enviar de preferencia
     * de la misma marca que el enviado por parametro, si no hay misma marca retornamos
     * la primer opción disponible.
     * @return String El Id del Item encontrado
    */
    public function getNextItemForSend(WaMsgDto $waMsg, String $mrkpref): array
    {
        $mrkpref = trim(mb_strtolower($mrkpref));
        $return = ['idDbSr' => 0, 'cant' => 0];

        $cooler = $this->getContent('coolers', $waMsg->from.'.json');
        
        $cantItems = count($cooler);
        if($cantItems == 0) {
            return $return;
        }

        // Si el cotizador dijo [NO TENGO LA MARCA] eliminamos todas los
        // items de esa misma marca.
        if($waMsg->subEvento == 'ntga') {
            for ($i=0; $i < $cantItems; $i++) { 

                $theMrk = trim(mb_strtolower($cooler[$i]['mrk']));
                if($theMrk == $mrkpref) {
                    unset($cooler[$i]);
                }
            }
            $cantItems = count($cooler);
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
            $return['idDbSr'] = $cooler[$has]['idDbSr'];
        }

        $return['cant'] = $cantItems;
        return $return;
    }

    /** 
     * Hacemos un resumen de todos los items con los que cuenta el cotizador
     * @return array La lista de los idDbSr
    */
    public function getResumeCooler(String $waId): array
    {    
        $return = [];
        $cooler = $this->getContent('coolers', $waId . '.json');
        $cants = count($cooler);
        if($cants > 0) {
            $return = array_column($cooler, 'idDbSr');
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
     * Cuando un cotizador presiona el boton de cotizar via formulario por medio
     * de un mensaje de whatsapp guardamos un archivo con los datos del item
     * para cuando abra la app se descarge este archivo y se hidrate el formulario
    */
    public function setCotViaForm(String $folder, String $waId, array $data): void
    {
        $this->setContent($folder, $waId.'.json', $data);
    }
    
    /** 
     * Cuando un cotizador presiona el boton de cotizar via formulario por medio
     * de un mensaje de whatsapp guardamos un archivo con los datos del item
     * para cuando abra la app se descarge este archivo y se hidrate el formulario
    */
    public function updateTokenWapi(String $token): array
    {
        $result = ['abort' => true, 'body' => 'X No se encontró archivo'];
        $wapi = $this->getContent('tkwaconm', 'tkwaconm.json');
        if(array_key_exists('modo', $wapi)) {

            $waId[ $wapi['modo'] ]['tk'] = $token;
            $fechaActual = new \DateTime();
            $ahora = $fechaActual->format('Y-m-d\TH:i:s.v');
            if(array_key_exists('dateUpdate', $waId[ $wapi['modo'] ])) {
                $waId[ $wapi['modo'] ]['dateUpdate'] = $ahora;
                $fechaActual = $fechaActual->add(new \DateInterval('PT23H'));
                $waId[ $wapi['modo'] ]['lastCheck'] = $fechaActual->format('Y-m-d\TH:i:s.v');
            }
            $this->setContent('tkwaconm', 'tkwaconm.json', $wapi);
            $result = ['abort' => false, 'body' => ['time' => $ahora]];
        }
        return $result;
    }

    /** 
     * Recuperamos los datos del item a cotizar desde la app, esto sucede
     * al haber presionado el btn de formulario en el msg de cotizar solicitud
    */
    public function getCotViaForm($folder, $waId): array
    {
        $content = $this->getContent($folder, $waId.'.json');
        $this->delete($folder, $waId.'.json');
        return ['abort' => false, 'body' => $content];
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
