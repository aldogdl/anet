<?php

namespace App\Controller\Cotiza;

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
use App\Service\StatusRutas;
use App\Service\WebHook;

class GetController extends AbstractController
{

  #[Route('cotiza/get-all-marcas/', methods:['get'])]
  public function getAllMarcas(AO1MarcasRepository $marcasEm): Response
  {
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $marcasEm->getAllAsArray()
    ]);
  }

  #[Route('cotiza/get-status-ordenes/', methods:['get'])]
  public function getStatusOrdenes(StatusRutas $ruta): Response
  {
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $ruta->getAllRutas()
    ]);
  }

  #[Route('cotiza/get-modelos-by-marca/{idMarca}/', methods:['get'])]
  public function getModelosByMarca(AO2ModelosRepository $modsEm, $idMarca): Response
  {
    $dql = $modsEm->getAllModelosByIdMarca($idMarca);
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $dql->getScalarResult()
    ]);
  }

  #[Route('api/cotiza/get-user-by-campo/', methods:['get'])]
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

  #[Route('api/cotiza/is-tokenapz-caducado/', methods:['get'])]
  public function isTokenApzCaducado(): Response
  {
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
  }

  #[Route('api/cotiza/get-ordenes-by-own-and-seccion/{idUser}/{est}/', methods:['get'])]
  public function getOrdenesByOwnAndEstacion(
    OrdenesRepository $ordenes, int $idUser, String $est
  ): Response
  {
    $dql = $ordenes->getOrdenesByOwnAndEstacion($idUser, $est);
    $ordens = $dql->getScalarResult();
    return $this->json(['abort' => false, 'body' => $ordens]);
  }

  #[Route('api/cotiza/get-piezas-by-lst-ordenes/{idsOrdenes}/', methods:['get'])]
  public function getPiezasByListOrdenes(
    OrdenPiezasRepository $piezas, String $idsOrdenes
  ): Response
  {
    $dql = $piezas->getPiezasByListOrdenes($idsOrdenes);
    $pzas = $dql->getScalarResult();
    return $this->json(['abort' => false, 'body' => $pzas]);
  }

  #[Route('cotiza/open-share-img-device/{filename}/', methods:['get'])]
  public function openShareImgDevice(CotizaService $cotService, String $filename): Response
  {
    $cotService->openShareImgDevice($filename);
    return $this->json(['abort' => false]);
  }

  #[Route('api/cotiza/check-share-img-device/{filename}/{tipo}/', methods:['get'])]
  public function checkShareImgDevice(CotizaService $cotService, String $filename, String $tipo): Response
  {
    $result = $cotService->checkShareImgDevice($filename, $tipo);
    return $this->json([
      'abort' => false, 'msg' => $tipo, 'body' => $result
    ]);
  }

  #[Route('api/cotiza/fin-share-img-device/{filename}/', methods:['get'])]
  public function finShareImgDevice(CotizaService $cotService, String $filename): Response
  {
    $result = $cotService->finShareImgDevice($filename);
    return $this->json(['abort' => false, 'body' => $result]);
  }

  #[Route('api/cotiza/del-share-img-device/{filename}/', methods:['get'])]
  public function delShareImgDevice(CotizaService $cotService, String $filename): Response
  {
    $cotService->delShareImgDevice($filename);
    return $this->json(['abort' => false, 'body' => '']);
  }

  #[Route('api/cotiza/del-img-of-orden-tmp/{filename}/', methods:['get'])]
  public function removeImgOfOrdenToFolderTmp(CotizaService $cotService, String $filename): Response
  {
    $cotService->removeImgOfOrdenToFolderTmp($filename);
    return $this->json(['abort' => false, 'body' => '']);
  }

  #[Route('api/cotiza/delete-orden/{idOrden}/', methods:['get'])]
  public function deleteOrden(OrdenesRepository $ordenes, String $idOrden): Response
  {
    $result = $ordenes->removeOrden($idOrden);
    return $this->json(['abort' => false, 'body' => $result]);
  }

  #[Route('api/cotiza/del-pieza/{idPza}/', methods:['get'])]
  public function deletePiezaAntesDeSave(
    StatusRutas $rutas, CotizaService $cotService,
    OrdenesRepository $ordenEm, OrdenPiezasRepository $pzasEm,
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
          $stts = $rutas->getAllRutas();
          $sttOrd = $rutas->getEstOrdenSinPiezas($stts);
          $ordenEm->changeSttOrdenTo($result['body']['orden'], $sttOrd);
        }
        $result['body'] = [];
      }
    }
    return $this->json($result);
  }

  #[Route('api/cotiza/enviar-orden/{idOrden}/', methods:['get'])]
  public function enviarOrden(
    OrdenesRepository $ordenEm, OrdenPiezasRepository $pzasEm,
    CentinelaService $centinela, StatusRutas $rutas, WebHook $wh,
    $idOrden
  ): Response
  {
    $result = ['abort' => true, 'body' => []];
    $orden = $ordenEm->find($idOrden);
    if($orden) {

      $stts = $rutas->getAllRutas();
      $sttOrd = $rutas->getEstInitDeProcesosByOrden($stts);
      $ids = [];
      if(!is_array($idOrden)) {
        $ids = [$idOrden];
      }else{
        $ids = $idOrden;
      }
      $ordenEm->changeSttOrdenTo($ids, $sttOrd);

      $sttPza = $rutas->getEstInitDeLasPiezas($stts);
      $pzasEm->changeSttPiezasTo($idOrden, $sttPza);

      $dql = $pzasEm->getIdsPiezasByIdOrden($idOrden);
      $idsPzas = $dql->getArrayResult();

      $rota = count($idsPzas);
      $data = $centinela->getSchemaInit($sttPza);
      for ($i=0; $i < $rota; $i++) {
          $data['piezas'][] = $idsPzas[$i]['id'];
      }
      $data['idOrden'] = $idOrden;
      $data['version'] = (string) round(microtime(true) * 1000);
      $saved = false;
      $a = 1;

      do {
        if(!$saved) {
          $saved = $centinela->setNewOrden($data);
        }
        $a++;
      } while ($a <= 5);

      if($saved) {

        // Enviamos el evento de nueva orden
        $ordenEm->sendEventCreadaSolicitud(
          $idOrden, $this->getParameter('nifiFld'), $wh, $this->getParameter('getAnToken')
        );

        $data['stt'] = [
          'ord' => ['est' => $sttOrd['est'], 'stt' => $sttOrd['stt']],
          'pza' => ['est' => $sttPza['est'], 'stt' => $sttPza['stt']],
        ];
        $result = ['abort' => !$saved, 'msg' => 'si-save', 'body' => $data['stt']];
      }else{
        $result = ['abort' => false, 'msg' => 'no-save', 'body' => $data['stt']];
      }
    }

    return $this->json($result);
  }

  /**
   * Endpoint para simular una solicitud
   */
  #[Route('cotiza-simula-sol/{idSol}/', methods:["GET"])]
  public function simularSolicitudNueva(
    OrdenesRepository $ordenEm, WebHook $wh, int $idSol
  ): Response {

    $ordenEm->simiSendEventCreadaSolicitud(
      $idSol, $this->getParameter('nifiFld'), $wh, $this->getParameter('getAnToken')
    );
    return $this->json(['ok' => 'Trabajo Realizado']);
  }

}
