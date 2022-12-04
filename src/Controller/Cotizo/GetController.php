<?php

namespace App\Controller\Cotizo;

use App\Repository\FiltrosRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Service\FiltrosService;
use App\Service\OpenApp;
use App\Service\ScmService;

class GetController extends AbstractController
{

	#[Route('api/cotizo/is-token-caducado/', methods:['get'])]
	public function isTokenCaducado(): Response
	{
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
	}
	
	/** Recuperamos el id y los roles del usuario */
	#[Route('cotizo/get-user-by-campo/', methods:['get'])]
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

	/**
	 * Recuperamos la orden y sus piezas y hacemos un registro de orden vista desde cotizo
	 * */
	#[Route('cotizo/get-orden-and-pieza/{idOrden}/{idUser}', methods:['get'])]
	public function getOrdeneAndPieza(
		OrdenesRepository $ordenes, OpenApp $openApp, String $idOrden, String $idUser
	): Response
	{
		$dql = $ordenes->getOrdenAndPieza($idOrden);
		$orden = $dql->getArrayResult();

		// guardamos Un registro para saber quien abrio la app
		$openApp->setNewApertura($idUser);
		return $this->json([
			'abort' => false, 'msg' => 'ok', 'body' => ($orden) ? $orden[0] : []
		]);
	}

	#[Route('cotizo/get-ordenes-and-piezas/{callFrom}/{page}/{idUser}/{limit}/', defaults:['page' => 1], methods:['get'])]
	public function getOrdenesAndPiezas(
		OrdenesRepository $ordenes, OpenApp $openApp,
		String $callFrom, int $page, String $idUser, int $limit
	): Response
	{
		$ordens = [];
		$dql = $ordenes->getOrdenesAndPiezas($callFrom);
		$paginator = $ordenes->paginador($dql, $page, 'array', $limit);
		foreach ($paginator['results'] as $post) {
			$ordens[] = $post;
		}
		// guardamos Un registro para saber quien abrio la app
		$openApp->setNewApertura($idUser);
		return $this->json(['abort' => false, 'msg' => $paginator['data'], 'body' => $ordens]);
	}

	/**
	 * Creamos el archivo de visto.
	 */
	#[Route('cotizo/set-reg-of/{filename}/', methods:['get'])]
	public function setOrdenVista(
		ScmService $scm, FiltrosService $ntg, String $filename
	): Response
	{
		if(strpos($filename, 'see') !== false || strpos($filename, 'pap') !== false) {
			$scm->setNewRegType($filename);
		}
		if(strpos($filename, 'ntg') !== false) {
			$ntg->setNewRegNoTengo($filename);
		}
		return $this->json(['abort' => false, 'body' => 'que haces aqui']);
	}

	/** 
	 * Recibimos todas las ordenes que tiene el cotizador en su dispositivo guardadas 
	 * y retornamos las que ya no estan disponibles.
	 */
	#[Route('cotizo/get-all-my-ntg/{idCot}', methods:['get'])]
	public function getAllMyNoTengo(FiltrosService $filerFS, String $idCot): Response
	{
		$ntgo = $filerFS->getMyAllNtnByidCot($idCot);
		return $this->json(['abort' => false, 'body' => $ntgo]);
	}

}
