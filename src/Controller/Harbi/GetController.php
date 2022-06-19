<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenRespsRepository;
use App\Repository\ScmCampRepository;
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

  /** */
	#[Route('harbi/set-test-camping/{idC}/{target}/{idT}/', methods:['get'])]
	public function setTestCamping(
		ScmCampRepository $em, $idC, $target, $idT
	): Response
	{
    // return $this->json(['ups' => 'Sin Autorización']);
    $data = [
      'camp' => $idC,
      'target' => $target,
      'src' => ['id' => $idT],
      'avo' => 2,
      'own' => 4,
      'sendAt' => 'now',
    ];
    $em->setNewCampaing($data);
    return $this->json(['ok' => 'Campaña creada']);
  }

	/** */
	#[Route('harbi/get-campaings/{campas}/', methods:['get'])]
	public function getCampainsOf(
		ScmCampRepository $em, OrdenRespsRepository $resps, $campas
	): Response
	{
		$response = ['abort' => false, 'msg' => 'ok', 'body' => []];

		$ids = explode(',', $campas);
		$dql = $em->getCampaingsByIds($ids);
		$campaings = $dql->getArrayResult();
		$rota = count($campaings);
		if($rota > 0) {
			for ($i=0; $i < $rota; $i++) {
				$result = $resps->getTargetById($campaings[$i]['target'], $campaings[$i]['src']);
        $campaings[$i]['err'] = '0';
				if(!$result['abort']) {
					$campaings[$i][$campaings[$i]['target']] = $result['body'];
				}else{
          $campaings[$i]['err'] = $result['body'];
        }
			}

			$response['body'] = $campaings;
		}else{
			$response['abort']= true;
			$response['msg']  = 'ERROR';
			$response['body'] = 'No se encontraron las campañas ' . implode(',', $ids);
		}
    
		return $this->json($response);
	}
}
