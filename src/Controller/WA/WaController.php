<?php

namespace App\Controller\WA;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Service\WaConsumer;
use App\Service\Wa\PayloadExtractor;
use App\Service\Wa\WaSenderApi;
use App\Service\Wa\ExpedienteSender;
use App\Service\Wa\TokenObfuscator;

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
			if(mb_strpos($has, 'statuses') > 0) {
				if (isset($message['entry'][0]['changes'][0]['value']['statuses'])) {
					$statuses = $message['entry'][0]['changes'][0]['value']['statuses'];
					foreach ($statuses as $st) {
						if (($st['status'] ?? '') === 'failed' || !empty($st['errors'])) {
							$fbFailsDir = $this->getParameter('fbFails');
							if (!is_dir($fbFailsDir)) {
								@mkdir($fbFailsDir, 0777, true);
							}
							$failFilename = rtrim($fbFailsDir, '/\\') . '/status_fail_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.json';
							@file_put_contents($failFilename, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
						}
					}
				}
				return new Response('', 200);
			}

			// Extraer información estructurada usando PayloadExtractor
			$extracted = PayloadExtractor::extract($message ?? $has);

			// Si el mensaje recibido contiene la clave [exp], procesar y enviar las solicitudes del expediente
			if ($extracted['is_valid'] && !empty($extracted['exp_file'])) {
				$expFile = $extracted['exp_file'];
				$recipientWaId = $extracted['wa_id'];
				$prodSolsDir = $this->getParameter('prodSols');
				$fbFailsDir = $this->getParameter('fbFails');
				$dtaCtcDir = $this->getParameter('dtaCtc');
				$waToken = $this->getParameter('waGrandTkn');
				$phoneId = !empty($extracted['phone_number_id']) ? $extracted['phone_number_id'] : null;

				$waSenderApi = new WaSenderApi();
				$expSender = new ExpedienteSender($waSenderApi);
				$res = $expSender->processAndSend(
					$expFile,
					$recipientWaId,
					$prodSolsDir,
					$waToken,
					$phoneId,
					$fbFailsDir,
					$dtaCtcDir
				);

				// Si falló el envío y NO es un error crítico de autenticación/token, notificar al usuario por WhatsApp
				if (!$res['success'] && !$res['is_auth_error'] && !empty($recipientWaId)) {
					$cleanExpName = preg_replace('/\.json$/i', '', trim($expFile));
					$errText = "⚠️ *Aviso de Solicitudes* ⚠️\n\n";
					$errText .= "❌ No fue posible entregar tu expediente:\n";
					$errText .= "📄 *{$cleanExpName}*\n\n";
					if (!empty($res['error'])) {
						$errText .= "🛑 *Detalle:* " . substr($res['error'], 0, 150) . "\n\n";
					}
					$errText .= "🔄 Puedes intentar reenviar este mensaje de expediente nuevamente.";

					$waSenderApi->sendTextMessage($recipientWaId, $errText, $waToken, $phoneId);
				}
			}

			// $consumer->exe($message, ($test == '') ? false : true);
		}
		return new Response('', 200);
	}

	/**
	 * Ruta para entregar el token de WhatsApp ofuscado en 3 partes
	 */
	#[Route('wa/get-secure-tkn', name: 'wa_get_secure_tkn', methods: ['GET', 'POST'])]
	public function getSecureToken(): Response
	{
		$rawToken = (string) $this->getParameter('waGrandTkn');
		$obfuscated = TokenObfuscator::obfuscate($rawToken);

		return $this->json($obfuscated, 200);
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

		$targetDir = $this->getParameter('prodSols');
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

