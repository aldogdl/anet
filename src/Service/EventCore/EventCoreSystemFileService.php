<?php

namespace App\Service\EventCore;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class EventCoreSystemFileService
{   
	private $params;
	private $filesystem;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
		$this->filesystem = new Filesystem();
	}
	
	/** */
	public function reSortImage(String $folder, array $paths):void
	{
		$prefixId = '';
		$origenes = [];
		$rotaP = count($paths);
		for ($i=0; $i < $rotaP; $i++) { 
			if($i == 0) {
				$partes = explode('_', $paths[$i]['origin']);
				$prefixId = $partes[0];
			}
			$origenes[] = $paths[$i]['origin'];
		}
		if($prefixId == '') {
			return;
		}

		// Movemos todas las fotos a un folder temporal
		$pathTmp = Path::canonicalize($folder.'/tmp');
		if(!$this->filesystem->exists($pathTmp)) {
			$this->filesystem->mkdir($pathTmp);
		}

		$finder = new Finder();
		$finder->files()->in($folder)->name($prefixId .'*');
		
		if($finder->hasResults()) {
			foreach ($finder as $file) {

				$filename = $file->getFilename();
				if(in_array($filename, $origenes)) {
					if(strpos($filename, $prefixId) !== false) {
						$origen = $folder.'/'.$filename;
						if($this->filesystem->exists($origen)) {
							try {
								$this->filesystem->rename($origen, $pathTmp.'/'.$filename, true);
							} catch (FileException $e) {}
						}
					}
				}
			}
		}
		
		// Regresar solo aquellas fotos que son reordenadas con su nombre correcto
		for ($i=0; $i < $rotaP; $i++) {
			$origen = $pathTmp.'/'.$paths[$i]['origin'];
			if($this->filesystem->exists($origen)) {
				try {
					$this->filesystem->rename(
						$origen, $folder.'/'.$paths[$i]['target'], true
					);
				} catch (FileException $e) {}
			}
		}
	}

	/** Guardamos el json resultante del alta de solicitud desde AnetShop */
	public function setSolicitudInFile(array $product): String
	{
		$olds = [];
		$path = $this->params->get('prodSols');
		$path = Path::canonicalize($path.'/'.$product['attrs']['slug'].'/inv_anet.json');
		if($this->filesystem->exists($path)) {
			$olds = json_decode(file_get_contents($path), true);
		}
		if($product['id'].'' == '-1') {
			$product['id'] = time();
		}
		array_unshift($olds, $product);

		try {
			$this->filesystem->dumpFile($path, json_encode($olds));
		} catch (FileException $e) {
			return 'X ' . $e->getMessage();
		}
		
		return $product['id'];
	}

	/** */
	public function getSolsTallerOf(String $slug): array
	{
		$data = [];
		$path = $this->params->get('prodSols');
		$filename = $path.'/'.$slug.'/inv_anet.json';
		if($this->filesystem->exists($filename)) {
			$data = json_decode(file_get_contents($filename), true);
		}
		return $data;
	}

	/** */
	public function getAllSolicitantes(): array
	{
		$data = [];
		$path = $this->params->get('dtaCtc');
		$finder = new Finder();
		$path = Path::canonicalize($path);
		$finder->files()->in($path)->depth('== 0')->contains('/ROLE_SOLZ/i');;
		if ($finder->hasResults()) {
			foreach ($finder as $file) {
				array_push($data, json_decode($file->getContents(), true));
			}
		}
		return $data;
	}

	/** */
	public function deleteSolicitud(array $product): String
	{
		$data = [];
		$path = $this->params->get('prodSols');
		$filename = $path.'/'.$product['slug'].'/inv_anet.json';

		if($this->filesystem->exists($filename)) {

			$data = json_decode(file_get_contents($filename), true);
			$rota = count($data);
			$item = -1;
			$newData = [];
			for ($i=0; $i < $rota; $i++) { 
				if($data[$i]['uuid'] == $product['uuid'] || $data[$i]['permalink'] == $product['permalink']) {
					$item = $i;
				}else{
					array_push($newData, $data[$i]);
				}
			}

			$this->filesystem->dumpFile($filename, json_encode($newData));
			if($item != -1) {

				$fotos = $data[$item]['fotos'];
				$rota = count($fotos);
				if($rota > 0) {
					$pathF = $path.'/'.$product['slug'].'/images/';
					for ($i=0; $i < $rota; $i++) { 
						if($this->filesystem->exists($pathF.$fotos[$i])) {
							unlink( $pathF.$fotos[$i] );
						}
					}
				}
				return 'ok';
			} else {
				return 'La solicitud no fué encontrada';
			}
		}

		return 'No hay archivo de '.$product['slug'];
	}

	/** */
	public function fileCmdExist(String $filename) : bool
	{
		$path = $this->params->get('waCmds');
		$pathA = Path::canonicalize($path . '/' . trim($filename));
		if($this->filesystem->exists($pathA)) {
			$this->filesystem->remove($pathA);
			return true;
		}
		return false;
	}

	/** 
	 * Archivo que administra los comandos enviados a nuestros repositorios
	 * para manejar los comandos WA_API
	*/
	public function fileSesionManager(String $filename, String $mode) : bool | array
	{
		$path = $this->params->get('waCmds');

		$pathA = Path::canonicalize($path . '/' . trim($filename) .'.json');
		if($mode == 'create') {
			file_put_contents($pathA, '');
			return true;
		}else if($mode == 'exist') {
			if($this->filesystem->exists($pathA)) {
				return true;
			}
		}else if($mode == 'delete') {
			if($this->filesystem->exists($pathA)) {
				$this->filesystem->remove($pathA);
				return true;
			}
		}else {
			if($this->filesystem->exists($pathA)) {
				$content = json_decode(file_get_contents($pathA), true);
				unlink($pathA);
				return $content;
			}
			return [];
		}

		return false;
	}

}
