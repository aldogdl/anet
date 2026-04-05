<?php

namespace App\Service;

use App\Service\Any\PublicAssetUrlGenerator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadService
{

	private ImageManager $imageManager;
  
	/** */
	public function __construct(
		private string $projectDir,
		private string $imageDriver,
		private PublicAssetUrlGenerator $assetUrlGenerator
	) {
    
		$this->imageManager = $this->buildManager();
	}
  
	/** */
	private function buildManager(): ImageManager
	{
		
		$driver = strtolower($this->imageDriver);
		if ($driver === 'imagick' && extension_loaded('imagick')) {
			return new ImageManager(new ImagickDriver());
		}

		if ($driver === 'gd' && extension_loaded('gd')) {
			return new ImageManager(new GdDriver());
		}

		if (extension_loaded('imagick')) {
			return new ImageManager(new ImagickDriver());
		}

		if (extension_loaded('gd')) {
			return new ImageManager(new GdDriver());
		}

		throw new \RuntimeException('No hay drivers disponibles: ni imagick ni gd.');
	}

	/** */
	public function uploadAndCreateThumb(UploadedFile $file, string $slug, string $iku, bool $withThubn): array
	{
		$imgDir = $this->projectDir . '/inv/images/' . $slug . '/' . $iku;
		if (!is_dir($imgDir)) {
			mkdir($imgDir, 0775, true);
		}

		$originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
		$extension = strtolower($file->guessExtension() ?: 'jpg');
		$mimeType = (string) $file->getMimeType();
		$allowedMimeTypes = [
			'image/jpeg' => 'jpg',
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		];

		if (!isset($allowedMimeTypes[$mimeType])) {
			throw new \RuntimeException('Formato de imagen no permitido. Solo JPG, PNG y WEBP.');
		}

		$filename = $originalName . '.' . $extension;
		$originalPath = $imgDir . '/' . $filename;
		$file->move($imgDir, $filename);
		if($withThubn === false) {
			return [
				'filename' => $filename,
				'base_path' => $this->assetUrlGenerator->generate($imgDir),
				'original_url' => $this->assetUrlGenerator->generate($originalPath),
				'thumb_url' => '',
			];
		}

		$thumbPath = $imgDir . '/peq_' . $filename;
		$image = $this->imageManager->read($originalPath);
		$image->scaleDown(width: 150);
		$image->save($thumbPath, 80);

		return [
			'filename' => $filename,
			'original_url' => $this->assetUrlGenerator->generate($originalPath),
			'thumb_url' => $this->assetUrlGenerator->generate($thumbPath),
		];
	}
}
