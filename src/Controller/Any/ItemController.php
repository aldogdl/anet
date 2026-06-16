<?php

namespace App\Controller\Any;

use App\Repository\ItemPubRepository;
use App\Repository\PaginatorQuery;
use App\Repository\SysComRepository;
use Symfony\Component\Filesystem\Path;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\ImageUploadService;
use App\Service\Pushes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Psr\Log\LoggerInterface;
use ZipArchive;

#[Route('/any-item')]
class ItemController extends AbstractController
{

	/**
	 * Endpoint para subir las imagenes desde el form del catalogo
	 */
	#[Route('/only-test', methods: ['get'])]
	public function onlyTest(ItemPubRepository $em): Response {
    
	  $res = $em->getIfExistPubById(1);
		if($res) {
			dd($res);
		}
		return $this->json(['abort' => true, 'body' => 'X No se ha subido la foto'], 401);
	}

	/** */
	#[Route('/pub', methods: ['get', 'post', 'delete'])]
	public function itemPub(
		Request $req, ItemPubRepository $repo, Fsys $fsys, 
		SysComRepository $sysCom, Pushes $push
	): Response
	{

		if($req->getMethod() == 'POST' ) {

			$data = $req->getContent();
			if($data) {

				$data = json_decode($data, true);
				$diccPath = $this->getParameter(AnyPath::$DICC);
				if(array_key_exists('list', $data)) {
					$res = $repo->setPubs($data, $diccPath);
					if($res != 0) {
						return $this->json(['abort' => false, "body" => $res]);
					}
				}else{
					$res = $repo->setPub($data, $diccPath);
					if(array_key_exists('abort', $res) && $res['abort']) {
						return $this->json($res, 500);
					}
					if($res != 0) {
						return $this->json($res);
					}
				}
			}

		} elseif( $req->getMethod() == 'DELETE' ) {

			$id = $req->query->get('id') ?? 0;
			$waId = $req->query->get('waId') ?? 0;
			$slug = $req->query->get('slug') ?? '';
			$dev = $req->query->get('dev') ?? 'desktop';

			if($id && $waId) {

			  if(mb_strpos($id, ',') !== false) {
					$ids = array_map('trim', explode(',', $id));
					if(count($ids) == 0) {
						return $this->json(['abort' => true, "body" => 'Sin Ids para eliminar'], 400);
					}
					if($ids[0] == '0') {
						unset($ids[0]);
						sort($ids);
					}
					$res = $repo->pausarPubByIdSrc($ids, $waId, $dev);
				} else {
					$res = $repo->pausarPub((int)$id, $waId, $dev);
				}

				if($res['success'] === false) {
					return $this->json(['abort' => true, "body" => $res['error'] ?? 'Error desconocido'], 400);
				}

				// Aprovechamos y limpiamos la BD y folders de Imagenes
				if($res['rowsAffected'] > 0) {
					$res = 'Publicación pausada correctamente';
					$del = $repo->deleteOldPausedItems();
					if($del['success']) {
						if($del['rowsDeleted'] > 0) {
							$fsys->deleteImages($del['imageData']);
						}
					}
				} else {
					$res = 'No se encontró la publicación o ya estaba pausada';
				}

				// Envio de noti desde desktop a movil
				$waIdExcepto = '0';
				if($dev == 'desktop' || $dev == 'web') {
					$waIdExcepto = (string)$waId;
				}

				$users = $sysCom->getTokensBySlug($slug, $waIdExcepto);
				$pay = [
					'event' => 'sync_centinela',
					'waId' => $waId.'',
					'slug' => $slug.'',
					'device' => $dev,
					'title' => 'Sincronizacion Centinela',
					'body' => 'Ejecutando Sincronizacioón desde el Centinela',
				];
				$push->sendMultiple($users, $pay);

				return $this->json(['abort' => false, "body" => $res]);

			}

		} elseif( $req->getMethod() == 'GET' ) {

			$id = $req->query->get('id') ?? 0;
			$page = $req->query->get('page') ?? 1;
			$waId = $req->query->get('waId') ?? 0;
			$slug = $req->query->get('slug') ?? 0;
			$items = [];

			if($id) {
				if(mb_strpos($id, ',') !== false) {
					$ids = array_map('trim', explode(',', $id));
					$items = $repo->getAllItemsByIds((string)$slug, $ids);
				} else {
					$items = $repo->getIfExistPubByIdToArray((int)$id);
				}
			} elseif($slug && $waId) {

				$query = $repo->getPubsBySlug($slug, $waId);
				$paginado = new PaginatorQuery();
				$items = $paginado->pagine($query, 50, 'max', $page);
			} else {
				return $this->json(['abort' => true, "body" => 'Parámetros incompletos'], 400);
			}
			return $this->json(['abort' => false, 'body' => $items]);
		}

		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

	/**
	 * Endpoint para subir las imagenes desde el form
	 */
	#[Route('/images', methods: ['POST'])]
	public function images(Request $req, ImageUploadService $imageUploadService, ItemPubRepository $em): Response
	{
		$ikuItem = $req->request->get('ikuItem');
		$slug = $req->request->get('slug');
		$idItem = $req->request->get('thubn');
		$withThubn = $req->request->get('thubn');
		$file = $req->files->get('file');

		if (!$ikuItem || !$slug || !$file) {
			return $this->json([
				'abort' => true,
				'body' => 'Parámetros incompletos',
			], 400);
		}

		if (!$withThubn) {
			$withThubn = false;
			$idItem = 0;
		}else {
			$withThubn = true;
			$idItem = (int) $idItem;
		}

		try {

			$result = $imageUploadService->uploadAndCreateThumb(
				$file,
				(string) $slug,
				(string) $ikuItem,
				$withThubn
			);

			$body = 'Imagen subida correctamente';
      if($idItem > 0 && array_key_exists('base_path', $result)) {
				$body = $em->updateImagePath($idItem, $result['filename'], $result['base_path']);
			}

			return $this->json([
				'abort' => false,
				'body' => $body,
				'filename' => $result['filename'],
				'original_url' => $result['original_url'],
				'thumb_url' => $result['thumb_url'],
			]);

		} catch (\Throwable $e) {
			return $this->json([
				'abort' => true,
				'body' => 'X Error al subir imagen: ' . $e->getMessage(),
			], 500);
		}
	}

	/**
	 * Endpoint para cargar archivos ZIP pesados de importación
	 * POST /any-item/up-import-items
	 * 
	 * Parameters:
	 * - file: Archivo ZIP a cargar (form-data)
	 * - slug: Identificador único para el archivo (form-data)
	 * 
	 * Response:
	 * - 200: Archivo guardado correctamente
	 * - 400: Parámetros incompletos o archivo inválido
	 * - 413: Archivo demasiado grande
	 * - 422: No es un archivo ZIP válido
	 * - 500: Error interno del servidor
	 */
	#[Route('/up-import-items', methods: ['POST'])]
	public function upZipItems(Request $request, LoggerInterface $logger): Response
	{
		try {
			// 1. Obtener parámetros
			$uploadedFile = $request->files->get('file');
			$slug = $request->request->get('slug');

			if (!$uploadedFile || !$slug) {
				return $this->json([
					'success' => false,
					'message' => 'Parámetros requeridos: file y slug'
				], Response::HTTP_BAD_REQUEST);
			}

			// 2. Validaciones de seguridad
			$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
			if (empty($slug)) {
				return $this->json([
					'success' => false,
					'message' => 'El slug contiene caracteres inválidos'
				], Response::HTTP_BAD_REQUEST);
			}

			// 3. Validar tamaño máximo (500MB)
			$maxSize = 500 * 1024 * 1024; // 500MB
			$fileSize = $uploadedFile->getSize();
			if ($fileSize > $maxSize) {
				return $this->json([
					'success' => false,
					'message' => sprintf('Archivo demasiado grande. Máximo: %dMB', $maxSize / 1024 / 1024)
				], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
			}

			// 4. Validar que sea un ZIP
			$mimeType = $uploadedFile->getMimeType();
			$extension = $uploadedFile->guessExtension();
			
			if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed']) && $extension !== 'zip') {
				return $this->json([
					'success' => false,
					'message' => 'El archivo debe ser un ZIP válido'
				], Response::HTTP_UNPROCESSABLE_ENTITY);
			}

			// 5. Validar integridad del ZIP
			$tempPath = $uploadedFile->getRealPath();
			$zip = new ZipArchive();
			$zipStatus = $zip->open($tempPath);

			if ($zipStatus !== true) {
				$logger->warning('ZIP file validation failed', [
					'slug' => $slug,
					'zip_status' => $zipStatus,
					'file_size' => $fileSize
				]);
				return $this->json([
					'success' => false,
					'message' => 'El archivo ZIP no es válido o está corrupto'
				], Response::HTTP_UNPROCESSABLE_ENTITY);
			}
			$zip->close();

			// 6. Crear directorio de destino si no existe
			$destDir = Path::canonicalize(
				$this->getParameter(AnyPath::$INVEXP) . 'export'
			);
			
			if (!is_dir($destDir)) {
				if (!@mkdir($destDir, 0755, true)) {
					throw new \RuntimeException("No se pudo crear el directorio: {$destDir}");
				}
			}

			// 7. Generar nombre de archivo final
			$filename = $slug . '.zip';
			$destination = $destDir . DIRECTORY_SEPARATOR . $filename;

			// 8. Guardar archivo usando move_uploaded_file (seguro)
			if (!$uploadedFile->move($destDir, $filename)) {
				throw new FileException('Error al guardar el archivo en el servidor');
			}

			// 9. Verificar que el archivo se guardó correctamente
			if (!file_exists($destination)) {
				throw new \RuntimeException('El archivo no se guardó correctamente');
			}

			$savedSize = filesize($destination);
			
			$logger->info('ZIP file uploaded successfully', [
				'slug' => $slug,
				'filename' => $filename,
				'original_size' => $fileSize,
				'saved_size' => $savedSize,
				'path' => $destination
			]);

			return $this->json([
				'success' => true,
				'message' => "Archivo ZIP '{$slug}' recibido y guardado correctamente",
				'data' => [
					'filename' => $filename,
					'slug' => $slug,
					'size' => $savedSize,
					'path' => "/inv/export/{$filename}"
				]
			], Response::HTTP_OK);

		} catch (FileException $e) {
			$logger->error('File exception during ZIP upload', [
				'error' => $e->getMessage(),
				'slug' => $slug ?? 'unknown'
			]);
			return $this->json([
				'success' => false,
				'message' => 'Error al procesar el archivo: ' . $e->getMessage()
			], Response::HTTP_INTERNAL_SERVER_ERROR);

		} catch (\Throwable $e) {
			$logger->error('Unexpected error during ZIP upload', [
				'error' => $e->getMessage(),
				'slug' => $slug ?? 'unknown',
				'trace' => $e->getTraceAsString()
			]);
			return $this->json([
				'success' => false,
				'message' => 'Error interno del servidor'
			], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	/** */
	#[Route('/dicc', methods: ['get'])]
	public function getDicc(Fsys $fsys): Response
	{
		return $this->json($fsys->getDiccionary());
	}

	/** */
	#[Route('/export-db', methods: ['post'])]
	public function exportDB(Request $request, Fsys $fsys): Response
	{

		$uploadedFile = $request->files->get('db_file');
		$customName = $request->request->get('file_name');

		if (!$uploadedFile) {
			return $this->json(['error' => 'No se envió el archivo'], Response::HTTP_BAD_REQUEST);
		}

		// Si no se envía nombre, usa el original
    $finalName = $customName ?: $uploadedFile->getClientOriginalName();
		$path = Path::canonicalize($this->getParameter(AnyPath::$INVEXP));

		try {
			$uploadedFile->move($path, $finalName);
    } catch (FileException $e) {
			return $this->json(['error' => 'Error guardando el archivo: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

		return $this->json([
			'success' => true,
			'message' => "Archivo {$finalName} recibido y guardado correctamente"
    ]);
	}

	/** */
	#[Route('/match-one', methods: ['post'])]
	public function matchOne(Request $req, ItemPubRepository $em): Response
	{
		if( $req->getMethod() != 'POST' ) {
			return $this->json(['abort' => true, 'body' => 'Método no permitido'], 405);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'X No se ha enviado el body'], 400);
		}

		$data = json_decode($data, true);
		$res = $em->matchOne($data);
		if($res != 0) {
			return $this->json(['abort' => false, "body" => $res]);
		}
		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

	/**
	 * Endpoint para obtener el idSr de un item a partir de su idSrc
	 */
	#[Route('/item-pub/check', methods: ['GET'])]
	public function checkItemPub(Request $req, ItemPubRepository $repo): Response
	{
		$idSrc = $req->query->get('idSrc');
		if (!$idSrc) {
			return new Response('0', Response::HTTP_BAD_REQUEST);
		}

		$item = $repo->findOneBy(['idSrc' => $idSrc]);
		$idSr = $item ? $item->getId() : 0;

		return new Response((string) $idSr);
	}

}
