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
	public function upImgToFolderTmp(array $data, $img): String
	{
		if($data['action'] == 'publik') {
			$path = $this->params->get('imgPublik');
		}else{
			$path = $this->params->get('imgOrdTmp');
		}

		$path = Path::canonicalize($path.'/'.$data['slug']);
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

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function setNewProduct(array $product): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$filename = $product['own']['waId'] . '-' . $product['id'] . '-' . $product['uuid'] . '.json';

		$path = $this->params->get('nifiFld');
		$path = Path::canonicalize($path.'/'.$filename);
		try {
			$this->filesystem->dumpFile($path, json_encode($product));
		} catch (FileException $e) {
			$result['abort'] = true;
			$result['body'] = $e->getMessage();
		}

		$fotos = [];
		$rota = count($product['piezas']);
		for ($i=0; $i < $rota; $i++) {

			$vueltas = count($product['piezas'][$i]['fotos']);
			for ($f=0; $f < $vueltas; $f++) { 
				$fotos[] = $product['piezas'][$i]['fotos'][$f];
			}
		}

		if(count($fotos) > 0) {
			$fotosFaltan = $this->checkIntegridadDeFotos(
				$product['action'], $product['own']['slug'], $fotos
			);
			if(count($fotos) > 0) {
				$result['faltan_fotos'] = $fotosFaltan;
			}
		}

		return $result;
	}

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function markProductAs(array $product): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$prefix = ($product['head']['waId'] == 'publik') ? 'vendida' : 'complete';
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

	///
	public function checkIntegridadDeFotos(String $action, String $slug, array $fotos): array
	{
		if($action == 'publik') {
			$path = $this->params->get('imgPublik');
		}else{
			$path = $this->params->get('imgOrdTmp');
		}

		$path = Path::canonicalize($path.'/'.$slug);
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

	/** */
	public function removeImgToFolderTmp(string $imgFileName): bool
	{
		$path = $this->params->get('imgOrdTmp');
		$pathTo = Path::canonicalize($path.'/'.$imgFileName);
		if($this->filesystem->exists($pathTo)) {
			$this->filesystem->remove($pathTo);
		}
		if($this->filesystem->exists($pathTo)) {
			return true;
		}
		return false;
	}

}
