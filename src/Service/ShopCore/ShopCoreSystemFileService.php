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
		$isRename = false;
		if($data['action'] == 'publik') {
			$path = $this->params->get('imgPublik');
		}else{
			$path = $this->params->get('imgOrdTmp');
		}
		$path = $path . '/' . $data['slug'];
		$data['pathServer'] = $path;
		file_put_contents('subiendo.json', json_encode($data));

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

	/** */
	public function upImgOfOrdenToFolderTmp(string $nombreArchivo, $img): String
	{
		$isRename = false;
		$path = $this->params->get('imgOrdTmp');
		if(!$this->filesystem->exists($path)) {
			$this->filesystem->mkdir($path);
		}
		$pathTo = Path::canonicalize($path.'/'.$nombreArchivo);
		// Necesitamos ver si es la imagen compartida y revisar los nombres
		// para ver si ya existen.
		if(strpos($nombreArchivo, 'share-') !== false) {
			if($this->filesystem->exists($pathTo)) {
				$newNombre = $this->determinarNuevoNombre($nombreArchivo);
				if($nombreArchivo != $newNombre) {
					$isRename = true;
					$nombreArchivo = $newNombre;
				}
			}
		}

		try {
			$img->move($path, $nombreArchivo);
		} catch (FileException $e) {
			return $e->getMessage();
		}

		if($this->filesystem->exists($pathTo)) {
			if($isRename) {
				return 'rename::' . $nombreArchivo;
			}
			return 'ok';
		}
		return 'not';
	}

	/** */
	public function removeImgOfOrdenToFolderTmp(string $imgFileName): bool
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
