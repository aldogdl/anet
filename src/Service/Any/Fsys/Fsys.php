<?php

namespace App\Service\Any\Fsys;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class Fsys
{

	private Filesystem $filesystem;
	private ParameterBagInterface $params;

	public function __construct(ParameterBagInterface $contenedor)
	{
		$this->params = $contenedor;
		$this->filesystem = new Filesystem();
	}
	
	/** */
	public function _parsePath(String $path, ?String $filename): string
	{
		$full = Path::canonicalize($this->params->get($path));
		if(!$this->filesystem->exists($full)) {
			$this->filesystem->mkdir($full, 0755);
		}
		if($filename != null) {
			if(mb_strpos($filename, '+') !== false) {
				$folders = explode('+', $filename);
				for ($i=0; $i < count($folders); $i++) { 
					if(mb_strpos($folders[$i], '.') === false) {
						if(!$this->filesystem->exists($full.'/'.$folders[$i])) {
							$this->filesystem->mkdir($full.'/'.$folders[$i], 0755);
						}
					}
				}
				$filename = implode('/', $folders);
			}
			return Path::canonicalize($full.'/'.$filename);
		}
		return $full;
	}

	/**
	 * Construimos el path desde los Enum
	 */
	public function buildPath(String $path, ?String $filename): string
	{
		return $this->_parsePath($path, $filename);
	}

	/** 
	 * El paquete de refacciones para mostrar en el catalogo
	 * de los usuarios finales
	*/
	public function getPackageOf(String $slug): array
	{
		$path = $this->_parsePath('prodPubs', $slug.'.json');
		try {
			$content = file_get_contents($path);
			if($content) {
				return json_decode($content, true);
			}
		} catch (\Throwable $th) {}
		return [];
	}

	/** */
	public function setPackageOf(String $slug, array $pakage): array
	{
		$path = $this->_parsePath('prodPubs', $slug.'.json');
		try {
			file_put_contents($path, json_encode($pakage));
		} catch (\Throwable $th) {}
		return [];
	}

	/** */
	public function getDiccionary(): array
	{
		$path = Path::canonicalize($this->params->get(AnyPath::$DICC));
		if($this->filesystem->exists($path)) {
			$content = file_get_contents($path);
			if($content) {
				$bytes = mb_strlen($content);
				$decode = json_decode($content, true);
				if($bytes > 1700) {
					file_put_contents($path, json_encode($decode));
				}
				return $decode;
			}
		}
		return [];
	}

	/** 
	 * El path es relativo a public_html ej. /folder/archivo
	*/
	public function getByPath(String $relative) : array
	{
		$path = Path::canonicalize($relative);
		if($this->filesystem->exists($path)) {
			$content = file_get_contents($path);
			if($content) {
				return json_decode($content, true);
			}
		}
		return [];
	}

	/** */
	public function get(String $path, ?String $filename) : array
	{
		$path = $this->_parsePath($path, $filename);
		if($this->filesystem->exists($path)) {
			$content = file_get_contents($path);
			if($content) {
					return json_decode($content, true);
			}
		}
		return [];
	}

	/** 
	 * El path ya esta construido junto con su nombre de archivo
	*/
	public function setByPath(String $path, array $content) : String
	{
		try {
			$this->filesystem->dumpFile($path, json_encode($content));
			return $path;
		} catch (\Throwable $th) {
			return $th->getMessage();
		}
		return '';
	}

	/** */
	public function set(String $path, array $content, ?String $filename) : String
	{
		$path = $this->_parsePath($path, $filename);
		try {
			$this->filesystem->dumpFile($path, json_encode($content));
		} catch (\Throwable $th) {
			return $th->getMessage();
		}
		return '';
	}

	/** */
	public function del(String $path, ?String $filename): bool
	{
		$path = $this->_parsePath($path, $filename);
		try {
			$this->filesystem->remove($path);
			return true;
		} catch (\Throwable $th) {}
		return false;
	}

	/** 
	 * Elimina las carpetas completas de imágenes de items eliminados.
	 * Array esperado: [['slug' => '...', 'iku' => '...'], ...]
	*/
	public function deleteImages(array $imageData): array
	{
		$result = [
			'success' => true,
			'deleted' => 0,
			'failed' => [],
			'errors' => []
		];

		if (empty($imageData)) {
			return $result;
		}

		foreach ($imageData as $item) {

			$slug = $item['slug'] ?? null;
			$iku = $item['iku'] ?? null;

			if (!$slug || !$iku) {
				$result['failed'][] = [
					'slug' => $slug,
					'iku' => $iku,
					'error' => 'slug o iku vacío'
				];
				continue;
			}

			try {
				// Construir la ruta: inv/images/{slug}/{iku}
				$imagePath = Path::canonicalize(
					$this->params->get('invExp') . '/images/' . $slug . '/' . $iku
				);

				if ($this->filesystem->exists($imagePath)) {
					$this->filesystem->remove($imagePath);
					$result['deleted']++;
				}
			} catch (\Throwable $th) {
				$result['success'] = false;
				$result['failed'][] = [
					'slug' => $slug,
					'iku' => $iku,
					'error' => $th->getMessage()
				];
			}
		}

		return $result;
	}

	/** Marcamos el inicio de sesion de un usuario */
	public function initSesion(String $waId, int $time): void 
	{
		if($waId == '') {
			return;
		}
	}
	
}
