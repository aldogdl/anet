<?php

namespace App\Service;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Finder\Finder;

class CotizaService
{   
	private $params;
	private $filesystem;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
		$this->filesystem = new Filesystem();
	}
	
	/**
	 * Optenemos el mensaje correspondiente de accion para el cliente.
	 */
	public function getTipoDeStatusByKey(string $key): string
	{
		$ruta = $this->params->get('msgCli');

		if($this->filesystem->exists($ruta)) {
			$msgs = json_decode( file_get_contents($ruta), true );
			if(array_key_exists($key, $msgs)) {
				return $msgs[$key];
			}
		}
		return '';
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
	public function upImgOfRespuestaToOrden(string $nombreArchivo, $img): String
	{
		$path = $this->params->get('imgOrdRsp');
		if(!$this->filesystem->exists($path)) {
			$this->filesystem->mkdir($path);
		}
		$pathTo = Path::canonicalize($path.'/'.$nombreArchivo);
		try {
			$img->move($path, $nombreArchivo);
		} catch (FileException $e) {
			return $e->getMessage();
		}

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

	/** */
	public function saveFileSharedImgFromDevices(array $data): bool
	{
			$finder = new Finder();
			$pathImgs = $this->params->get('imgOrdTmp');
			if(!$this->filesystem->exists($pathImgs)) {
					$this->filesystem->mkdir($pathImgs);
			}else{
					$finder->files()->in($pathImgs)->name('*'.$data['orden'] .'-'. $data['idPiezaTmp'] .'*');
					if ($finder->hasResults()) {
							foreach ($finder as $file) {
									$data['files'][] = $file->getRelativePathname();
							}
					}
			}
			
			$path = $this->params->get('shareimgDev');
			if(!$this->filesystem->exists($path)) {
					$this->filesystem->mkdir($path);
			}
			$pathTo = Path::canonicalize($path.'/'.$data['filename'].'.json');
			file_put_contents($pathTo, json_encode($data));
			if($this->filesystem->exists($pathTo)) {
					return true;
			}
			return false;
	}

	/** */
	public function openShareImgDevice(String $filename)
	{
			$path = $this->params->get('shareimgDev');
			$path = $path . '/' . $filename . '.json';
			if($this->filesystem->exists($path)) {
					$data = json_decode(file_get_contents($path), true);
					$data['isOpen'] = true;
					$this->filesystem->dumpFile($path, json_encode($data));
			}
	}

	/**
	 * Guardamos el nonbre de la imagen en el archivo indicado para compartir imagenes
	 * @param String $nombreArchivo El nombre del archivo json para compartir
	 * @param String $filename El nombre de la imagen compartida
	*/
	public function updateFilenameInFileShare(String $nombreArchivo, String $filename)
	{
			$path = $this->params->get('shareimgDev');
			$path = Path::canonicalize($path . '/' . $nombreArchivo . '.json');
			$data = json_decode(file_get_contents($path), true);
			if(!in_array($filename, $data['files'])) {
					$data['files'][] = $filename;
			}
			$this->filesystem->dumpFile($path, json_encode($data));
	}

	/**
	 * Marcamos como fin de la carga de imagenes
	*/
	public function finShareImgDevice(String $nombreArchivo)
	{
			$path = $this->params->get('shareimgDev');
			$path = Path::canonicalize($path . '/' . $nombreArchivo . '.json');
			$data = json_decode(file_get_contents($path), true);
			$data['isFinish'] = true;
			$this->filesystem->dumpFile($path, json_encode($data));
	}

	/**
	 * Marcamos como fin de la carga de imagenes
	*/
	public function delShareImgDevice(String $nombreArchivo)
	{
			$path = $this->params->get('shareimgDev');
			$path = Path::canonicalize($path . '/' . $nombreArchivo . '.json');
			$this->filesystem->remove($path);
	}

	/** */
	public function checkShareImgDevice(String $filename, String $tipoChequeo): array
	{
			$path = $this->params->get('shareimgDev');
			$path = $path . '/' . $filename . '.json';
			$data = [];
			if($this->filesystem->exists($path)) {
					$data = json_decode(file_get_contents($path), true);
			}
			
			$res = false;
			if($tipoChequeo == 'isOpen') {
					if(array_key_exists('isOpen', $data)) {
							if($data['isOpen']) {
									$res = true;
							}
					}
			}

			if($tipoChequeo == 'fotos') {
					if(array_key_exists('files', $data)) {
							return [
									'result' => true,
									'isFinish' => (array_key_exists('isFinish', $data)) ? true : false,
									'fotos'  => $data['files']
							];
					}
			}
			return ['result' => $res];
	}
}
