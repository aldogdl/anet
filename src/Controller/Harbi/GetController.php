<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Service\CentinelaService;
use App\Service\FiltrosService;
use App\Service\ScmService;

class GetController extends AbstractController
{

  #[Route('harbi/get-user-by-campo/', methods:['get'])]
  public function getUserByCampo(NG2ContactosRepository $contacsEm, Request $req): Response
  {
    $campo = $req->query->get('campo');
    $valor = $req->query->get('valor');
    $user = $contacsEm->findOneBy([$campo => $valor]);
    $result = [];
    $abort = true;
    if($user) {
      $abort = false;
      $result = $contacsEm->toArray($user);
    }
    return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
  }

  /**
   * Descargamos el contenido del centinela cuando el FTP no funciona
   */
  #[Route('harbi/download-centinela/', methods:['get'])]
  public function downloadCentinela(CentinelaService $centinela): Response
  {
    return $this->json(
      ['abort' => false, 'msg' => 'ok','body' => $centinela->downloadCentinela()]
    );
  }

  /**
   * Checamos prueba de conexion con retorno sencillo
   */
  #[Route('harbi/check-connx/', methods:['get'])]
  public function checkConnx(): Response
  {
    return $this->json(['abort'=>false, 'body' => 'ok']);
  }

  /**
   * Checamos la ultima version del archivo de seguimiento de las ordenes
   */
  #[Route('harbi/check-changes/{lastVersion}/', methods:['get'])]
  public function checkCheckChanges(
    CentinelaService $centinela, ScmService $scm,
    FiltrosService $filtros, $lastVersion
  ): Response
  {
    $result = ['hay' => false];

    $isSame = $centinela->isSameVersion($lastVersion);
    $result['hay'] = !$isSame;

    $scm = $scm->getContent(true);
    if(count($scm) > 0) { $result['hay'] = true; }

    $filtros = $filtros->getContent(true);
    if(count($filtros) > 0) { $result['hay'] = true; }

    $result['changes'] = ['scm' => $scm, 'filtros' => $filtros, 'centinela' => !$isSame];

    return $this->json(['abort'=>false, 'body' => $result]);
  }

  /**
   * Recuperamos la orden por medio de su ID
   */
  #[Route('harbi/get-orden-by-id/{idOrden}/', methods:['get'])]
  public function getOrdenById(OrdenesRepository $ordEm, $idOrden): Response
  {
    $dql = $ordEm->getDataOrdenById($idOrden);
    $data = $dql->getScalarResult();
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => (count($data) > 0) ? $data[0] : []
    ]);
  }

  /**
   * Recuperamos las marcas y modelos
   */
  #[Route('harbi/get-all-autos/', methods:['get'])]
  public function getAllAutos(AO1MarcasRepository $mksEm): Response
  {
    $dql = $mksEm->getAllAutos();
    $data = $dql->getArrayResult();
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
  }
}
