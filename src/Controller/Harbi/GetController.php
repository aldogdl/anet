<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Repository\OrdenRespsRepository;
use App\Repository\ScmCampRepository;
use App\Repository\ScmReceiversRepository;
use App\Service\CentinelaService;
use App\Service\FiltrosService;
use App\Service\OrdenesAsigns;
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
  public function checkChanges(
    CentinelaService $centinela, ScmService $scm,
    FiltrosService $filtros, $lastVersion, OrdenesAsigns $ordAsigns
  ): Response
  {
    $isSame = $centinela->isSameVersion($lastVersion);
    $scmSee = $scm->hasRegsOf('see');
    $scmResp = $scm->hasRegsOf('rsp');
    $scm = $scm->getContent(true);
    $filtros = $filtros->getContent(true);
    $asigns  = $ordAsigns->hasContent();

    $result = [
      'centinela' => !$isSame, 'scmSee' => $scmSee, 'scmResp' => $scmResp,
      'scm' => $scm, 'filtros' => $filtros, 'asigns' => $asigns
    ];

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

  /**
   * Usado solo para guardar una campaña de prueba 
  */
	#[Route('harbi/set-test-camping/{idC}/{target}/{idT}/', methods:['get'])]
	public function setTestCamping(
		ScmCampRepository $em, $idC, $target, $idT
	): Response
	{
    return $this->json(['ups' => 'Sin Autorización']);
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

	/**
   * Recuperamos los archivos que representan registros de descargas, vistas y
   * respuestas de los cotizadores ante los mensajes enviados por whatsapp
  */
	#[Route('harbi/get-filesreg-of/{type}/', methods:['get'])]
	public function getFilesRegOf(ScmService $scm, String $type): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $r['body'] = $scm->getAllRegsOf($type);
		return $this->json($r);
	}

  /** Recuperamos las respuestas y colocamos el nuevo statu a las piezas */
	#[Route('harbi/get-resp-by-ids/{ids}/{ver}', methods:['get'])]
	public function getRespuestaByIds(
    OrdenRespsRepository $rpsEm, OrdenPiezasRepository $pzaEm,
    CentinelaService $centi, $ids, $ver
  ): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $partes = explode(',', $ids);

    // Recuperamos las respuestas
		$dql = $rpsEm->getRespuestaByIds($partes);
    $resps = $dql->getArrayResult();
    
    $rota = count($resps);
    if($rota > 0) {
      $r['body'] = $resps;
      $idPasz = [];
      for ($i=0; $i < $rota; $i++) { 
        $idPasz[] = $resps[$i]['pieza']['id'];
      }
    }

    if(count($idPasz) > 0) {
      $data = [
        'piezas' => $idPasz, 'stts' => ['est' => 4, 'stt' => 1], 'version' => $ver
      ];
      
      // Cambiamos el status en la BD
      $pzaEm->changeSttByIdsPiezas($data['piezas'], $data['stts']);
      // Cambiamos el status en el Centinela
      $centi->setNewSttToPiezasByIds($data);
    }

		return $this->json($r);
	}

	/**
   * Actualizamos los status de los registros que representan descargas, vistas y
   * respuestas de los cotizadores ante los mensajes enviados por whatsapp
  */
	#[Route('harbi/set-regs-byids/{ids}/{stt}/', methods:['get'])]
	public function setSttRegsByIds(ScmReceiversRepository $em, String $ids, String $stt): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $em->setSttRegsByIds($ids, $stt);
		return $this->json($r);
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

	/** */
	#[Route('harbi/get-all-ordspzas/{ords}/', methods:['get'])]
	public function getAllOrdsPzas(
		OrdenPiezasRepository $piezas, $ords
	): Response
	{
		$response = ['abort' => false, 'msg' => 'ok', 'body' => []];

		$ids = explode(',', $ords);
		$dql = $piezas->getAllOrdsPzas($ids);
		$ordenes = $dql->getArrayResult();
		if(count($ordenes) > 0) {
			$response['body'] = $ordenes;
		}else{
			$response['abort']= true;
			$response['msg']  = 'ERROR';
			$response['body'] = 'No se encontraron las ordenes ' . implode(',', $ids);
		}
    
		return $this->json($response);
	}

  #[Route('harbi/get-all-cotz/', methods:['get'])]
  public function getAllCotizadores(NG2ContactosRepository $contactos): Response
  {   
    $dql = $contactos->getAllCotizadores(true);
    return $this->json(['abort'=>false,'msg'=>'ok','body'=>$dql->getScalarResult()]);
  }
}
