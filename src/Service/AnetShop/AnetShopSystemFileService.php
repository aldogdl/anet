<?php

namespace App\Service\AnetShop;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class AnetShopSystemFileService
{   
	private $params;
	private $filesystem;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
		$this->filesystem = new Filesystem();
	}
	
	/** */
	public function getInv(String $waId, $slug): array
	{
		$data = [];

		$path = $this->params->get('prodPubs');
		$filename = $path.'/'.$slug.'/inv_anet.json';
		if($this->filesystem->exists($filename)) {
			$data = json_decode(file_get_contents($filename), true);
		}
		
		$filename = $this->params->get('invCtc') . $waId . '_up.json';
		if($this->filesystem->exists($filename)) {
			$otros = json_decode(file_get_contents($filename), true);
			$data = array_merge($data, $otros);
		}

		return $data;
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

		$error = '';
		if(array_key_exists('rename', $data)) {
			try {
				$this->filesystem->rename($path.'/'.$data['filename'], $path.'/'.$data['rename'], true);
			} catch (FileException $e) {
				$error = 'X '.$e->getMessage();
			}
		}

		try {
			$img->move($path, $data['filename']);
		} catch (FileException $e) {
			return 'X '.$e->getMessage();
		}

		$pathTo = Path::canonicalize($path.'/'.$data['filename']);
		if($this->filesystem->exists($pathTo)) {
			return 'ok';
		}
		$error = 'X No se guardo la Imagen';
		return $error;
	}

	/** 
	 * Limpiamos las imagenes que no esten incluidas en el registro
	*/
	public function cleanImgToFolder(array $data, String $modo): String
	{
		if($modo == 'publik') {
			$path = $this->params->get('prodPubs');
		}else{
			$path = $this->params->get('prodSols');
		}

		$id = $data['product']['uuid'];
		$slug = $data['meta']['slug'];

		$path = Path::canonicalize($path.'/'.$slug.'/images');
		if(!$this->filesystem->exists($path)) {
			return 'X No hay fotografías';
		}

		// Recoger las fotos actuales
		$fotosCurrents = [];
		$finder = new Finder();
		$finder->files()->in($path)->name($id .'*');
		if ($finder->hasResults()) {
			foreach ($finder as $file) {
				$fotosCurrents[] = $file->getFilename();
			}
		}
		
		if(count($fotosCurrents) == 0) {
			return 'X No hay fotografías';
		}

		$rota = count($data['product']['fotos']);
		for ($i=0; $i < $rota; $i++) { 

			$pathFile = Path::canonicalize($path.'/'.$data['product']['fotos'][$i]);
			if($this->filesystem->exists($pathFile)) {
				$has = array_search($data['product']['fotos'][$i], $fotosCurrents);
				if($has !== false) {
					unset($fotosCurrents[$has]);
				}
			}
		}

		$fotosCurrents = array_values($fotosCurrents);
		$rota = count($fotosCurrents);
		for ($i=0; $i < $rota; $i++) { 
			$pathFile = Path::canonicalize($path.'/'.$fotosCurrents[$i]);
			unlink($pathFile);
		}

		return 'ok';
	}

	/** 
	 * Limpiamos las imagenes por que el usuario cancelo la operacion
	*/
	public function cleanForCancelImgToFolder(String $modo, String $id, String $slug): String
	{
		if($modo == 'publik') {
			$path = $this->params->get('prodPubs');
		}else{
			$path = $this->params->get('prodSols');
		}

		$path = Path::canonicalize($path.'/'.$slug.'/images');
		if($this->filesystem->exists($path)) {
			$finder = new Finder();
			$finder->files()->in($path)->name($id .'*');
			if ($finder->hasResults()) {
				foreach ($finder as $file) {
					unlink($file->getRealPath());
				}
			}
		}

		return 'ok';
	}

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function setNewProduct(array $product, String $filename): String
	{
		$path = $this->params->get('nifiFld');
		$path = Path::canonicalize($path.'/'.$filename);
		try {
			$this->filesystem->dumpFile($path, json_encode($product));
		} catch (FileException $e) {
			$path = 'X ' . $e->getMessage();
		}

		return '';
	}

	/** Guardamos el json resultante del alta de productos desde shopCore */
	public function markProductAs(array $product): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$prefix = ($product['payload']['src'] == 'publik') ? 'vendida' : 'complete';
		$filename = $prefix .'-'. $product['head']['slug'] . '-' . $product['head']['fecha'] . '.json';
		
		$path = $this->params->get('nifiFld');
		if(!$this->filesystem->exists($path)) {
			$this->filesystem->mkdir($path);
		}
		$path = Path::canonicalize($path.'/'.$filename);
		try {
			$this->filesystem->dumpFile($path, json_encode($product));
		} catch (FileException $e) {
			$result['abort'] = true;
			$result['body'] = $e->getMessage();
		}
		return $result;
	}

	/** Guardamos el json de los comentarios o sugerencias desde shopCore */
	public function saveLogError(array $error): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$filename = $error['filename'] . '.json';
		
		$path = $this->params->get('logErrs');
		if(!is_dir($path)) {
			mkdir($path);
		}
		$path = Path::canonicalize($path.'/'.$filename);
		$error['path'] = $path;
		try {
			$this->filesystem->dumpFile($path, json_encode($error));
		} catch (FileException $e) {
			$result['abort'] = true;
			$result['body'] = $e->getMessage();
		}
		return $result;
	}

	/** Guardamos el json de los comentarios o sugerencias desde shopCore */
	public function saveComments(array $product): array
	{
		$result = ['abort' => false, 'body' => 'ok'];
		$filename = $product['user']['slug'] . '-' . $product['user']['waId'] . '.json';
		
		$path = $this->params->get('comments');
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
