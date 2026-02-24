<?php

namespace App\Controller\Any;

use App\Repository\ItemPubRepository;
use Symfony\Component\Filesystem\Path;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\PublicAssetUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/any-item')]
class ItemController extends AbstractController
{
  
	/**
	 * Endpoint para subir las imagenes desde el form del catalogo
	 */
	#[Route('/only-test', methods: ['get'])]
	public function onlyTest(Request $req, ItemPubRepository $em): Response {
    
	  $res = $em->getIfExistPubById(1);
		if($res) {
			dd($res);
		}
		return $this->json(['abort' => true, 'body' => 'X No se ha subido la foto'], 401);
	}

	/**
	 * Endpoint para subir las imagenes desde el form del catalogo
	 */
	#[Route('/image', methods: ['POST'])]
	public function imagesUP(Request $req, PublicAssetUrlGenerator $urlGen): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['abort' => true, 'body' => 'X No se ha subido la foto'], 401);
		}
		
		$ikuItem = $req->request->get('ikuItem') ?? null;
		$key = $req->request->get('key') ?? null;
		$file = $req->files->get('file');

		if (!$ikuItem || !$key || !$file) {
			return $this->json(['abort' => true, 'body' => 'Parámetros incompletos'], 400);
		}

		$prodSols = $this->getParameter(AnyPath::$PRODSOLS);
		$path = Path::canonicalize($prodSols);

		if (!file_exists($path)) {
			try {
				mkdir($path, 0755, true);
			} catch (\Throwable $th) {
				return $this->json(['abort' => true, 'body' => 'X Error al crear carpeta' . $path], 402);
			}
		}
		
		try {
			$originalFilename = basename($file->getClientOriginalName());
			$file->move($path, $originalFilename);
		} catch (\Throwable $e) {
			return $this->json(['abort' => true, 'body' => 'X Error al mover archivo: '.$e->getMessage()], 500);
		}

		// Un refuerzo para guardarlo
		$path = Path::canonicalize($prodSols.'/'.$originalFilename);
		if (!file_exists($path)) {
			try {
				$file->move($path, $originalFilename);
			} catch (\Throwable $th) {
				return $this->json(['abort' => true, 'body' => 'X Error al mover archivo' . $path], 402);
			}
		}

		$url = $urlGen->generate($path);
		$results = [
			'abort' => false,
			'body' => $url,
			'filename' => $originalFilename,
		];

		return $this->json($results);
	}

	/** */
	#[Route('/pub', methods: ['get', 'post', 'delete'])]
	public function itemPub(Request $req, ItemPubRepository $repo): Response
	{
		if( $req->getMethod() == 'POST' ) {

			$data = $req->getContent();
			$dicc = $this->getParameter(AnyPath::$DICC);

			if($data) {

				$data = json_decode($data, true);
				if(array_key_exists('list', $data)) {
					$res = $repo->setPubs($data, $dicc);
					if($res != 0) {
						return $this->json(['abort' => false, "body" => $res]);
					}
				}else{
					$res = $repo->setPub($data, $dicc);
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
			if($id && $waId) {
				$res = $repo->delPub($id, $waId);
				if($res != 0) {
					return $this->json(['abort' => false, "body" => $res]);
				} else {
					return $this->json(['abort' => true, "body" => 'Parámetros incompletos'], 400);
				}
			}

		} elseif( $req->getMethod() == 'GET' ) {
			$items = [];
			return $this->json($items, 200);
		}

		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
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
		/** @var UploadedFile $uploadedFile */
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
}
