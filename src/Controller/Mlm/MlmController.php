<?php

namespace App\Controller\Mlm;

use App\Repository\SyncMlRepository;
use App\Service\DataSimpleMlm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MlmController extends AbstractController
{
	/**
	 * Endpoint para la verificacion de conección
	 */
	#[Route('mlm/notifications/', methods: ['GET', 'POST'])]
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
	 * Endpoint para la verificacion de conección
	 */
	#[Route('mlm/code/', methods: ['GET', 'POST'])]
	public function verifyMlm(Request $req): Response
	{
		$slug = $req->query->get('state');
		$code = $req->query->get('code');
		if(mb_strlen($code) > 10) {
			file_put_contents('mlm_'.$slug.'.txt', $code);
			return new Response(file_get_contents('shop/mlm_exito.html'));
		}
		return new Response('Bienvenido a ANY->MLM', 200);
	}

	/**
	 * Recuperar las ultimas notificaciones
	 */
	#[Route('mlm/notif/get/', methods: ['GET'])]
	public function recoveryNotifMlm(Request $req, SyncMlRepository $em): Response
	{
		$query = $req->query->get('last');
		$msgs = $em->getAllMsgAfterByMsgId($query);
		return $this->json($msgs);
	}

	/**
	 * Endpoint para actualizar los datos lock provenientes desde la app
	 * del catalogo
	 */
	#[Route('mlm/refresh-token-mlm/{slug}/{refreshTk}', methods: ['GET'])]
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
	#[Route('mlm/parse-cot-token/{slug}/', methods: ['DELETE', 'GET'])]
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

		if($req->getMethod() == 'DELETE') {
			// $isOk = $mlm->desvincularMlm($slug);
			// if($isOk == 'ok') {
			//     return $this->json(['abort' => false, 'body' => ['ok' => $slug]]);
			// }
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
