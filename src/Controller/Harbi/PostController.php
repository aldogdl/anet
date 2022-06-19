<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ScmCampRepository;
use App\Service\CentinelaService;
use App\Service\HarbiConnxService;
use App\Service\StatusRutas;
use App\Service\ScmService;

class PostController extends AbstractController
{
	/**
	 * Obtenemos el request contenido decodificado como array
	 *
	 * @throws JsonException When the body cannot be decoded to an array
	 */
	public function toArray(Request $req, String $campo): array
	{
		$content = $req->request->get($campo);
		try {
			$content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new JsonException('No se puede decodificar el body.', $e->getCode(), $e);
		}
		if (!\is_array($content)) {
			throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
		}
		return $content;
	}

	#[Route('harbi/save-ip-address-harbi/', methods:['post'])]
	public function saveIpAdressHarbi(Request $req, HarbiConnxService $harbi): Response
	{   
		$data = $this->toArray($req, 'data');
		$harbi->saveIp($data);
		return $this->json(['abort'=>false, 'msg' => 'ok','body' => 'save']);
	}
	
	#[Route('harbi/save-ruta-last/', methods:['post'])]
	public function saveRutaLast(Request $req, StatusRutas $rutas): Response
	{
		$data = $this->toArray($req, 'data');
		$rutas->setNewRuta($data);
		return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => 'save']);
	}

	/*** */
	#[Route('harbi/get-campaings/', methods:['get'])]
	public function getCampainsOf(
		Request $req, ScmCampRepository $em, ScmService $scm,
		CentinelaService $centinela
	): Response
	{
		$response = ['abort' => false, 'msg' => 'ok', 'body' => []];

		// Obtenemos el contenido completo del archivo Targets.
		// Aqui conocemos cuales son los ids de las campañas nuevas
		$fileTargets = $this->toArray($req, 'data');
		$dql = $em->getCampaingsByIds($fileTargets);
		$campaings = $dql->getArrayResult();

		$rota = count($campaings);
		if($rota > 0) {

			// $fileCenti = $centinela->getContent();
			// // Obtenemos los targets de cada campaña
			// for ($i=0; $i < $rota; $i++) {
			// 	$emT = $doctrine->getRepository('App\\Entity\\'.$campaings[$i]['src']['class']);
			// 	$result = $emT->getTargetById($campaings[$i]['src']['id']);
			// 	if($result) {
			// 		$campaings[$i]['target'] = $result[0];

			// 		// Extraemos a los receiver de dicha campaña.
			// 		$piezasIds = $fileCenti['piezas'][$campaings[$i]['target']['id']];
			// 		$vultas = count($piezasIds);
			// 		$idsReceivers = [];
			// 		for ($p=0; $p < $vultas; $p++) {
			// 			$idsReceivers = array_merge($idsReceivers, $fileCenti['stt'][ $piezasIds[$p] ]['ctz']);
			// 		}
			// 		$idsReceivers = array_unique($idsReceivers);
			// 		sort($idsReceivers);
			// 		shuffle($idsReceivers);
			// 		$campaings[$i]['receivers'] = $idsReceivers;
			// 	}
			// }

			// $response['body'] = $campaings;
			// $scm->clean($target);
		}else{
			$response['abort']= true;
			$response['msg']  = 'ERROR';
			$response['body'] = 'No se encontraron las ordenes ' . implode(',', $fileTargets);
		}
		return $this->json($response);
	}

}
