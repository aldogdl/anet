<?php

namespace App\Controller\Cotizo;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Service\CentinelaService;
use App\Service\CotizaService;
use App\Service\ScmService;
use App\Service\StatusRutas;

class GetController extends AbstractController
{

	/** sin checar poder borrar */
	#[Route('cotizo/get-all-marcas/', methods:['get'])]
	public function getAllMarcas(AO1MarcasRepository $marcasEm): Response
	{
		return $this->json([
			'abort'=>false, 'msg' => 'ok',
			'body' => $marcasEm->getAllAsArray()
		]);
	}

	/** sin checar poder borrar */
	#[Route('cotizo/get-modelos-by-marca/{idMarca}/', methods:['get'])]
	public function getModelosByMarca(AO2ModelosRepository $modsEm, $idMarca): Response
	{
		$dql = $modsEm->getAllModelosByIdMarca($idMarca);
		return $this->json([
			'abort'=>false, 'msg' => 'ok',
			'body' => $dql->getScalarResult()
		]);
	}

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

	/** sin checar poder borrar */
	#[Route('api/cotizo/is-tokenapz-caducado/', methods:['get'])]
	public function isTokenApzCaducado(): Response
	{
		return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
	}

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

	/** sin checar poder borrar */
	#[Route('api/cotizo/get-piezas-by-lst-ordenes/{idsOrdenes}/', methods:['get'])]
	public function getPiezasByListOrdenes(
		OrdenPiezasRepository $piezas, String $idsOrdenes
	): Response
	{
		$dql = $piezas->getPiezasByListOrdenes($idsOrdenes);
		$pzas = $dql->getScalarResult();
		return $this->json(['abort' => false, 'body' => $pzas]);
	}

	/** sin checar poder borrar */
	#[Route('api/cotizo/del-pieza/{idPza}/', methods:['get'])]
	public function deletePiezaAntesDeSave(
		StatusRutas $rutas,	CotizaService $cotService,
		OrdenesRepository $ordenEm,	OrdenPiezasRepository $pzasEm,
		$idPza
	): Response
	{
		$result = $pzasEm->deletePiezaAntesDeSave($idPza);
		if(!$result['abort']) {

			if(array_key_exists('fotos', $result['body'])) {

				$rota = count($result['body']['fotos']);
				for ($i=0; $i < $rota; $i++) {
					$cotService->removeImgOfOrdenToFolderTmp($result['body']['fotos'][$i]);
				}
			}

			if(array_key_exists('orden', $result['body'])) {

				$piezasByOrden = $pzasEm->findBy(['orden' => $result['body']['orden']]);
				if(count($piezasByOrden) == 0) {
					$stts = $rutas->getRutaByFilename($result['body']['ruta']);
					$sttOrd = $rutas->getEstOrdenSinPiezas($stts);
					$ordenEm->changeSttOrdenTo($result['body']['orden'], $sttOrd);
				}
				$result['body'] = [];
			}
		}
		return $this->json($result);
	}

}
