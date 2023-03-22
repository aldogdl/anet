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
use App\Service\WebHook;

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
		OrdenesRepository $ordenes, OpenApp $openApp, WebHook $wh,
		String $idOrden, String $idUser
	): Response
	{
		$dql = $ordenes->getOrdenAndPieza($idOrden);
		$orden = $dql->getArrayResult();

		// guardamos Un registro para saber quien abrio la app
		$this->sendNotifictEvent((int) $idUser, (int) $idOrden, 'atendida_solicitud', $wh);
		// $openApp->setNewApertura($idUser);
		return $this->json([
			'abort' => false, 'msg' => 'ok', 'body' => ($orden) ? $orden[0] : []
		]);
	}

	#[Route('cotizo/get-ordenes-and-piezas/{callFrom}/{page}/{idUser}/{limit}/', defaults:['page' => 1], methods:['get'])]
	public function getOrdenesAndPiezas(
		OrdenesRepository $ordenes, OpenApp $openApp, WebHook $wh,
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
		$this->sendNotifictEvent((int) $idUser, 0, 'appertura_cotizo', $wh, $callFrom);
		// $openApp->setNewApertura($idUser);
		return $this->json(['abort' => false, 'msg' => $paginator['data'], 'body' => $ordens]);
	}

	/**
	 * Creamos el archivo de visto.
	 * antes ruta: cotizo/set-reg-of/{filename}/
	 */
	#[Route('cotizo/{filename}/', methods:['get'])]
	public function setOrdenVista(
		ScmService $scm, FiltrosService $ntg, WebHook $wh, String $filename
	): Response
	{
		// nth__435-0-2pp252__1678746986516.ntg
		// seca__274-40-2cc0__1678734510562.see
		$partes = explode('__', $filename);
		if(count($partes) < 2) {
			$rootNifi = $this->getParameter('nifiFld');
			file_put_contents(
				$rootNifi.'fails/fallo_cotizo_set_orden_vista_1.json',
				json_encode([
					'status' => 'error de filename',
					'ruta'  => 'cotizo/{filename}/',
					'body'   => $filename
				])
			);
			return $this->json(['abort' => false, 'body' => 'que haces aqui']);
		}
		$solicitud = 0;
		$cotizador = 0;
		$pieza = 0;
		$avo = 0;

		$accFrom = $partes[0];
		$pedazos = explode('-', $partes[1]);
		$solicitud = $pedazos[0];
		$cotizador = $pedazos[1];
		if(strpos($partes[1], 'pp') !== false){
			// Esta involucrada una pieza || 435-0-2pp252
			$pedazos2 = explode('pp', $pedazos[2]);
			$avo = $pedazos2[0];
			$pieza = $pedazos2[1];
		}
		if(strpos($partes[1], 'cc')  !== false){
			// Esta involucrada una solicitud || 274-40-2cc0
			$pedazos2 = explode('pp', $pedazos[2]);
			$avo = $pedazos2[0];
			$pieza = (count($pedazos2) > 1) ? $pedazos2[1] : '';
		}

		$accion = '';
		if(strpos($filename, 'see') !== false || strpos($filename, 'pap') !== false) {
			// $scm->setNewRegType($filename);
			$accion = 'vista_solicitud';
		}
		if(strpos($filename, 'ntg') !== false) {
			// $ntg->setNewRegNoTengo($filename);
			$accion = 'noTengo_solicitud';
		}
		// dd(
		// 	$accFrom,
		// 	$solicitud,
		// 	$cotizador,
		// 	$pieza,
		// 	$avo,
		// );
		$this->sendNotifictEvent(
			(int) $cotizador, (int) $solicitud, $accion, $wh, $accFrom, (int) $pieza, (int) $avo
		);
		return $this->json(['abort' => false, 'body' => 'que haces aqui']);
	}

	/**
	 * Este metodo esta duplicado, ya que en algun sistema que no se cual es, es
	 * esta ruta solicitada y entro sistema es la ruta de arriba la que se solicita.
	 * mal muy mal, se unificará en al siguiente version.
	 */
	#[Route('cotizo/set-reg-of/{filename}/', methods:['get'])]
	public function setOrdenVistaDos(
		ScmService $scm, FiltrosService $ntg, WebHook $wh, String $filename
	): Response
	{
		// nth__435-0-2pp252__1678746986516.ntg
		// seca__274-40-2cc0__1678734510562.see
		$partes = explode('__', $filename);
		if(count($partes) < 2) {
			$rootNifi = $this->getParameter('nifiFld');
			file_put_contents(
				$rootNifi.'fails/fallo_cotizo_set_orden_vista_1.json',
				json_encode([
					'status' => 'error de filename',
					'ruta'  => 'cotizo/{filename}/',
					'body'   => $filename
				])
			);
			return $this->json(['abort' => false, 'body' => 'que haces aqui']);
		}
		$solicitud = 0;
		$cotizador = 0;
		$pieza = 0;
		$avo = 0;

		$accFrom = $partes[0];
		$pedazos = explode('-', $partes[1]);
		$solicitud = $pedazos[0];
		$cotizador = $pedazos[1];
		if(strpos($partes[1], 'pp') !== false){
			// Esta involucrada una pieza || 435-0-2pp252
			$pedazos2 = explode('pp', $pedazos[2]);
			$avo = $pedazos2[0];
			$pieza = $pedazos2[1];
		}
		if(strpos($partes[1], 'cc')  !== false){
			// Esta involucrada una solicitud || 274-40-2cc0
			$pedazos2 = explode('pp', $pedazos[2]);
			$avo = $pedazos2[0];
			$pieza = (count($pedazos2) > 1) ? $pedazos2[1] : '';
		}

		$accion = '';
		if(strpos($filename, 'see') !== false || strpos($filename, 'pap') !== false) {
			// $scm->setNewRegType($filename);
			$accion = 'vista_solicitud';
		}
		if(strpos($filename, 'ntg') !== false) {
			// $ntg->setNewRegNoTengo($filename);
			$accion = 'noTengo_solicitud';
		}
		// dd(
		// 	$accFrom,
		// 	$solicitud,
		// 	$cotizador,
		// 	$pieza,
		// 	$avo,
		// );
		$this->sendNotifictEvent(
			(int) $cotizador, (int) $solicitud, $accion, $wh, $accFrom, (int) $pieza, (int) $avo
		);
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

	///
	private function sendNotifictEvent(
		int $idUser, int $idSol, string $event, WebHook $wh, string $accFrom = '',
		int $idPza = 0, int $idAvo = 0
	)
	{
        $payload = [
          "evento"    => 'whastapp_api',
		  "solicitud" => $idSol,
		  "piezas"    => $idPza,
		  "accion"    => $event,
		  "accFrom"   => $accFrom,
          "cotizador" => $idUser,
          "avo"       => $idAvo,
		  "source"   => "",
        ];
        $wh->sendMy($payload, $this->getParameter('nifiFld'), $this->getParameter('getAnToken'));
	}
}
