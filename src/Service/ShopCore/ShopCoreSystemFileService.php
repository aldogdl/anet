<?php

namespace App\Service\ShopCore;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ShopCoreSystemFileService
{   
	private $params;
	private $filesystem;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
		$this->filesystem = new Filesystem();
	}
	
	/**
	 * Buscamos un nuevo nombre para las imagenes compartidas que ya existen
	 */
	public function determinarNuevoNombre(String $filename): String 
	{
		$pathImgs = $this->params->get('imgOrdTmp');
		$withoutExt = explode('.', $filename);
		$partes = explode('-', $withoutExt[0]);
		$ext = $withoutExt[count($withoutExt)-1];
		$quitar = '-'.$partes[count($partes)-1].'.'.$ext;
		$name = str_replace($quitar, '', $filename);
		$files = [];

		$finder = new Finder();
		$finder->files()->in($pathImgs)->name('*'. $name .'*');
		if ($finder->hasResults()) {
			$files = [];
			foreach ($finder as $file) {
				$files[] = $file->getRelativePathname();
			}
			for ($i=0; $i < 20; $i++) {
				$filename = $name . '-' . $i+1 . '.' . $ext;
				if(!in_array($filename, $files)) {
					break;
				}
			}
		}
		
		return $filename;
	}

	/** */
	public function upImgToFolder(array $data, $img): String
	{
		if($data['action'] == 'publik') {
			$path = $this->params->get('prodPubs');
		}else{
			$path = $this->params->get('prodSols');
		}

		$path = Path::canonicalize($path.'/'.$data['slug'].'/images');
		if(!$this->filesystem->exists($path)) {
			$this->filesystem->mkdir($path);
		}

		try {
			$img->move($path, $data['filename']);
		} catch (FileException $e) {
			return $e->getMessage();
		}
		
		$pathTo = Path::canonicalize($path.'/'.$data['filename']);
		if($this->filesystem->exists($pathTo)) {
			return 'ok';
		}
		return 'not';
	}

	///
	public function checkIntegridadDeFotos(String $action, String $slug, array $fotos): array
	{
		$path = '';
		if($action == 'publik') {
			$path = $this->params->get('prodPubs');
		}
		if($action == 'cotiza') {
			$path = $this->params->get('prodSols');
		}
		if($path == '') {
			return [];
		}

		$path = Path::canonicalize($path.'/'.$slug.'/images');
		if(!$this->filesystem->exists($path)) {
			return $fotos;
		}

		$innexistentes = [];
		$rota = count($fotos);
		for ($i=0; $i < $rota; $i++) { 			
			$pathTo = Path::canonicalize($path.'/'.$fotos[$i]);
			if(!$this->filesystem->exists($pathTo)) {
				$innexistentes[] = $fotos[$i];
			}
		}

		return $innexistentes;
	}

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function setNewProduct(array $product): array
	{
		$payload = [
			'evento'  => '',
			'source'  => '',
			'payload' => ''
		];

		$result = ['abort' => false, 'body' => 'ok'];
		$filename = $product['own']['waId'] . '-' . $product['id'] . '-' . $product['uuid'] . '.json';

		$path = $this->params->get('nifiFld');
		$path = Path::canonicalize($path.'/'.$filename);
		$product['filename'] = $filename;

		try {
			$this->filesystem->dumpFile($path, json_encode($product));
		} catch (FileException $e) {
			$result['abort'] = true;
			$result['body'] = $e->getMessage();
		}

		$fotos = [];
		$path = '';
		
		$payload['source'] = $filename.'.json';
		if($product['action'] == 'publik') {
			$path = $this->params->get('prodPubs');
			$payload['evento'] = 'creada_publicacion';
		}
		if($product['action'] == 'cotiza') {
			$path = $this->params->get('prodSols');
			$payload['evento'] = 'creada_solicitud';
		}

		if($path != '') {

			$slug = $product['own']['slug'];
			// Primero recogemos todas las fotos de las piezas para revisar que esten
			// ya almacenadas en el servidor. 
			$rota = count($product['piezas']);
			for ($i=0; $i < $rota; $i++) {
				$vueltas = count($product['piezas'][$i]['fotos']);
				for ($f=0; $f < $vueltas; $f++) { 
					$fotos[] = $product['piezas'][$i]['fotos'][$f];
				}
			}

			// Ahora revisamos si hay piezas para publicar y no para solicitar.
			if(array_key_exists('pzaPublik', $product)) {
			
				$rota = count($product['pzaPublik']);
				for ($i=0; $i < $rota; $i++) {
					$filename = $path.'/'.$slug.'/'.$product['pzaPublik'][$i]['uuid'].'.json';
					try {
						$this->filesystem->dumpFile($filename, json_encode($product['pzaPublik'][$i]));
						// TODO enviar a nifi el aviso de nuevo producto...
					} catch (FileException $e) {}
				}
			}
		}

		// Revisamos la existencia de las fotos resultantes
		if(count($fotos) > 0) {
			$fotosFaltan = $this->checkIntegridadDeFotos(
				$product['action'], $product['own']['slug'], $fotos
			);
			if(count($fotosFaltan) > 0) {
				$result['faltan_fotos'] = $fotosFaltan;
			}
		}

		$payload['payload'] = $product;
		$result['forNifi']  = $payload;
		return $result;
	}

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function markProductAs(array $product): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$prefix = ($product['payload']['src'] == 'publik') ? 'vendida' : 'complete';
		$filename = $prefix .'-'. $product['head']['slug'] . '-' . $product['head']['fecha'] . '.json';
		
		$path = $this->params->get('nifiFld');
		$path = Path::canonicalize($path.'/'.$filename);
		try {
			$this->filesystem->dumpFile($path, json_encode($product));
		} catch (FileException $e) {
			$result['abort'] = true;
			$result['body'] = $e->getMessage();
		}
		return $result;
	}

	/** */
	public function removeImgToFolderTmp(string $imgFileName): bool
	{
		$path = $this->params->get('imgOrdTmp');
		$pathTo = Path::canonicalize($path . '/' . $imgFileName);
		if($this->filesystem->exists($pathTo)) {
			$this->filesystem->remove($pathTo);
		}
		if($this->filesystem->exists($pathTo)) {
			return true;
		}
		return false;
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
}
