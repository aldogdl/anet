<?php

namespace App\Controller\Mlm;

use App\Repository\SyncMlRepository;
use App\Service\Any\MeLi\MeliOAuthService;
use App\Service\DataSimpleMlm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

#[Route('/mlm')]
class MlmController extends AbstractController
{
	/**
	 * Endpoint para la verificacion de conección
	 */
	#[Route('/notifications/', methods: ['GET', 'POST'])]
	public function notisMlm(Request $req, SyncMlRepository $em): Response
	{
		$content = $req->getContent();
		if(mb_strpos($content, "_id")) {
			$map = json_decode($content, true);
			if(array_key_exists('user_id', $map)) {
				$em->set($map);
			}
		}
		return new Response('listo MLM', 200);
	}

	/**
	 * Endpoint receptor de callback OAuth de Mercado Libre.
	 *
	 * Delega la validación de state, consumo de nonce, canje de credenciales
	 * y notificación S2S a [MeliOAuthService].
	 */
	#[Route('/code/', methods: ['GET', 'POST'])]
	public function verifyMlm(Request $req, MeliOAuthService $oauthService): Response
	{
		// 1. Manejo de denegación/error devuelto directamente por Mercado Libre
		$errorFromMeli = (string) ($req->query->get('error') ?? '');
		if ($errorFromMeli !== '') {
			$desc = (string) ($req->query->get('error_description') ?? 'Autorización cancelada por el usuario');
			return new Response($this->renderErrorHtml('Autorización cancelada en Mercado Libre: ' . htmlspecialchars($desc)), Response::HTTP_BAD_REQUEST);
		}

		$state = (string) ($req->query->get('state') ?? '');
		$code = (string) ($req->query->get('code') ?? '');

		if ($state === '' || $code === '' || mb_strlen($code) < 10) {
			return new Response($this->renderErrorHtml('Parámetros de autorización incompletos o inválidos.'), Response::HTTP_BAD_REQUEST);
		}

		// 2. Validación criptográfica, integridad y expiración del state
		$validation = $oauthService->validateState($state);
		if (!$validation['valid']) {
			return new Response($this->renderErrorHtml($validation['error'] ?? 'State inválido'), $validation['statusCode']);
		}

		// 3. Consumo atómico del nonce (Anti-Replay)
		if (!$oauthService->consumeNonce($validation['nonce'])) {
			return new Response($this->renderErrorHtml('Este enlace de autorización ya ha sido utilizado previamente.'), Response::HTTP_BAD_REQUEST);
		}

		// 4. Canje de código con MeLi, persistencia JSON y notificación S2S al VPS
		$exchangeResult = $oauthService->exchangeAndNotify($code, $validation['slug']);
		if (!$exchangeResult['success']) {
			return new Response($this->renderErrorHtml($exchangeResult['error'] ?? 'Error al procesar credenciales'), $exchangeResult['statusCode']);
		}

		return new Response(
			$this->renderSuccessHtml($exchangeResult['slug'], (int) $exchangeResult['userId']),
			Response::HTTP_OK,
			['Content-Type' => 'text/html; charset=utf-8']
		);
	}

	private function renderSuccessHtml(string $slug, int $userId): string
	{
		return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Vinculación Exitosa - YonKeros</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
		.card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; text-align: center; max-width: 440px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
		.icon { width: 64px; height: 64px; background: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; font-size: 32px; color: white; }
		h1 { font-size: 22px; margin: 0 0 10px 0; color: #f8fafc; }
		p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin: 0 0 24px 0; }
		.badge { background: #334155; color: #38bdf8; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-family: monospace; display: inline-block; margin-bottom: 20px; }
		.btn { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
		.btn:hover { background: #1d4ed8; }
	</style>
</head>
<body>
	<div class="card">
		<div class="icon">✓</div>
		<h1>¡Vinculación Exitosa!</h1>
		<div class="badge">Empresa: {$slug} (ID: {$userId})</div>
		<p>Tu cuenta de Mercado Libre ha sido vinculada correctamente con YonKeros y sincronizada en tiempo real.</p>
		<button class="btn" onclick="window.close();">Cerrar esta ventana</button>
	</div>
</body>
</html>
HTML;
	}

	private function renderErrorHtml(string $message): string
	{
		return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Error de Vinculación - YonKeros</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
		.card { background: #1e293b; border: 1px solid #ef4444; border-radius: 16px; padding: 40px; text-align: center; max-width: 440px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
		.icon { width: 64px; height: 64px; background: #ef4444; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; font-size: 32px; color: white; }
		h1 { font-size: 22px; margin: 0 0 10px 0; color: #f8fafc; }
		p { color: #fca5a5; font-size: 14px; line-height: 1.5; margin: 0 0 24px 0; }
		.btn { background: #475569; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
		.btn:hover { background: #334155; }
	</style>
</head>
<body>
	<div class="card">
		<div class="icon">✕</div>
		<h1>Fallo de Vinculación</h1>
		<p>{$message}</p>
		<button class="btn" onclick="window.close();">Cerrar</button>
	</div>
</body>
</html>
HTML;
	}

	/**
	 * Endpoint para actualizar los datos lock provenientes desde la app
	 * del catalogo
	 */
	#[Route('/refresh-token-mlm/{slug}/{refreshTk}', methods: ['GET'])]
	public function refreshTokenMlm(DataSimpleMlm $mlm, String $slug, String $refreshTk): Response
	{
		$res = $mlm->refreshTokenMlm($slug, $refreshTk);
		return $this->json($res);
	}

	/**
	 * Al vincular mlm con anyShop se crea un json con los datos de dicha
	 * vinculacion por lo tanto se recuperan desde la app AnyShop y se
	 * eliminan inmediatamente.
	 */
	#[Route('/parse-cot-token/{slug}/', methods: ['DELETE', 'GET'])]
	public function mlmParseCodeToken(Request $req, DataSimpleMlm $mlm, String $slug): Response
	{
		if($req->getMethod() == 'GET') {

			$path = 'mlm_'.$slug.'.txt';
			if(!is_file($path)) {
				return $this->json(['abort' => false, 'body' => ['error' => 'X Aun no llega']]);
			}

			try {
				$code = file_get_contents($path);
				if($code) {
					$isOk = $mlm->parseCodeToToken($code, $slug);
					if(count($isOk) > 0) {
						unlink($path);
						return $this->json($isOk);
					}
				}
				return $this->json(['abort' => true, 'body' => ['error' => 'X Error en los datos']]);
			} catch (\Throwable $th) {
				return $this->json(['abort' => true, 'body' => ['error' => 'X ' . $th->getMessage()]]);
			}
		}

		return $this->json(['abort' => true, 'body' => ['error' => 'X Error desconocido']]);
	}

	/** 
	 * Desvinculamos la relacion entre app meli
	*/
	#[Route('/desvincular-meli', methods: ['POST'])]
	public function desvincularMeli(Request $req, DataSimpleMlm $mlm): Response
	{
		$data = $req->getContent();
		if($data) {
			$data = json_decode($data, true);
			if(array_key_exists('slug', $data)) {
				$res = $mlm->desvincularMlm($data);
				return $this->json(['result' => $res]);
			}
		}
		return $this->json(['result' => false]);
	}

}
