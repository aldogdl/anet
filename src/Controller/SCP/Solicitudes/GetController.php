<?php

namespace App\Controller\SCP\Solicitudes;

use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{
	#[Route('scp/get-ids-ordenes-by-avo/{idAvo}/', methods:['get'])]
	public function getIdsOrdenesByAvo(OrdenesRepository $ordEm, $idAvo): Response
	{   
		$dql = $ordEm->getAllIdsOrdenByAvo($idAvo);
		return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $dql->getScalarResult()]);
	}

	#[Route('scp/get-ordenes-by-avo/{idAvo}/{hydra}', defaults:['hydra' => 'scalar'], methods:['get'])]
	public function getOrdenesByAvo(OrdenesRepository $ordEm, $idAvo, $hydra): Response
	{   
		$dql = $ordEm->getAllOrdenByAvo($idAvo);
		return $this->json([
			'abort'=>false, 'msg' => 'ok',
			'body' => ($hydra == 'scalar') ? $dql->getScalarResult() : $dql->getArrayResult()
		]);
	}

	#[Route('scp/get-orden-by-id/{idOrden}/', methods:['get'])]
	public function getOrdenById(OrdenesRepository $ordEm, $idOrden): Response
	{   
		$dql = $ordEm->getDataOrdenById($idOrden);
		$data = $dql->getScalarResult();
		return $this->json([
			'abort'=>false, 'msg' => 'ok',
			'body' => (count($data) > 0) ? $data[0] : []
		]);
	}

	#[Route('scp/get-piezas-by-orden/{idOrden}/', methods:['get'])]
	public function getPiezasByOrden(OrdenPiezasRepository $pzasEm, $idOrden): Response
	{   
		$dql = $pzasEm->getPiezasByOrden($idOrden);
		return $this->json([
			'abort'=>false, 'msg' => 'ok',
			'body' => $dql->getScalarResult()
		]);
	}

}
