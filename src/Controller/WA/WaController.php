<?php

namespace App\Controller\WA;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Service\WaConsumer;

class WaController extends AbstractController
{
	/**
	 * Endpoint para la verificacion de conección
	 */
	#[Route('wa/wh/{test}', methods: ['GET', 'POST'])]
	public function verifyWa(Request $req, String $test = ''): Response
	{
		// WaConsumer $consumer,
		if($req->getMethod() == 'GET') {

			$verify = $req->query->get('hub_verify_token');
			if($verify == $this->getParameter('getWaToken')) {

				$mode = $req->query->get('hub_mode');
				if($mode == 'subscribe') {
					$challenge = $req->query->get('hub_challenge');
					return new Response($challenge);
				}
			}
		}

		if($req->getMethod() == 'POST') {
				
			$has = $req->getContent();
			if(strlen($has) < 50) {
				return $this->json( [], 500 );
			}
			
			$message = json_decode($has, true);
			if(mb_strpos($has, 'statuses') === false) {
				file_put_contents('mensaje_ws.json', json_encode($message));
			}
			// $consumer->exe($message, ($test == '') ? false : true);
		}
		return new Response('', 200);
	}

	/**
	 * Endpoint para recibir JSON de solicitudes y guardarlo en prod_sols
	 */
	#[Route('wa/receive-sols', name: 'wa_receive_sols', methods: ['POST'])]
	public function receiveSols(Request $req): Response
	{
		$content = $req->getContent();

		// Si viene como multipart/form-data con un campo 'payload' o 'json'
		if (empty($content) && $req->request->has('payload')) {
			$content = $req->request->get('payload');
		}

		if (empty($content)) {
			return $this->json([
				'status' => 'error',
				'message' => 'No content received'
			], 400);
		}

		$data = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE && !is_array($data)) {
			return $this->json([
				'status' => 'error',
				'message' => 'Invalid JSON payload'
			], 400);
		}

		$projectDir = $this->getParameter('kernel.project_dir');
		$targetDir = $projectDir . '/public_html/prod_sols';

		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0777, true);
		}

		$filename = 'sol_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.json';
		$filePath = $targetDir . '/' . $filename;

		// Guardar JSON formateado
		file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		// Manejo de imágenes adjuntas si aplica en multipart
		if ($req->files->count() > 0) {
			foreach ($req->files as $key => $uploadedFile) {
				if ($uploadedFile) {
					$imgName = 'sol_img_' . date('Ymd_His') . '_' . $key . '.' . ($uploadedFile->guessExtension() ?? 'jpg');
					$uploadedFile->move($targetDir, $imgName);
				}
			}
		}

		return $this->json([
			'status' => 'ok',
			'filename' => $filename,
			'message' => 'JSON guardado exitosamente en prod_sols'
		], 200);
	}

}

