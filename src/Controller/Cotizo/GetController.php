<?php

namespace App\Controller\Cotizo;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Service\FiltrosService;
use App\Service\ScmService;

class GetController extends AbstractController
{

	/** Recuperamos el id y los roles del usuario */
	#[Route('cotizo/get-user-by-campo', methods:['get'])]
	public function getUserByCampo(NG2ContactosRepository $contacsEm, Request $req): Response
	{
		$campo = $req->query->get('campo');
		$valor = $req->query->get('valor');
		$user = $contacsEm->findOneBy([$campo => $valor]);
		$result = [];
		$abort = true;
		if($user) {
			$abort = false;
			$result['u_id'] = $user->getId();
			$result['u_roles'] = $user->getRoles();
		}
		return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
	}

	/** Recuperamos la orden y sus piezas y hacemos un registro de orden vista desde cotizo */
	#[Route('cotizo/get-orden-and-pieza/{idOrden}/', methods:['get'])]
	public function getOrdeneAndPieza(
		OrdenesRepository $ordenes, ScmService $scm, String $idOrden
	): Response
	{
		$params = explode('&', $idOrden);
		$idOrden = $params[0];
		if(count($params) > 1) { $scm->setNewRegType($params[1]); }

		$dql = $ordenes->getOrdenAndPieza($idOrden);
		$orden = $dql->getArrayResult();
		return $this->json(
			['abort' => false, 'body' => (count($orden) > 0) ? $orden[0] : []
		]);
	}

	#[Route('cotizo/get-ordenes-and-piezas/{page}/', defaults:['page' => 1], methods:['get'])]
	public function getOrdenesAndPiezas(OrdenesRepository $ordenes, int $page): Response
	{
		$dql = $ordenes->getOrdenesAndPiezas($page);
		$ordens = $dql->getArrayResult();
		return $this->json(['abort' => false, 'body' => $ordens]);
	}

	#[Route('cotizo/set-new-filtro/{filtro}/', methods:['get'])]
	public function setNewFiltro(FiltrosService $filerFS, String $filtro): Response
	{
		// El parametro filtro debe venir con la extencion adecuada (.cnt | .cnm)
		$dql = $filerFS->setNewFiltro($filtro);
		return $this->json(['abort' => false, 'body' => 'ok']);
	}

}
