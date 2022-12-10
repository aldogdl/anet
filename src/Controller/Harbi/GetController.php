<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\Pushes;
use App\Service\ScmService;
use App\Service\OrdenesAsigns;
use App\Service\FiltrosService;
use App\Service\CentinelaService;
use App\Repository\OrdenesRepository;
use App\Repository\ScmCampRepository;
use App\Repository\AO1MarcasRepository;
use App\Repository\CampaingsRepository;
use App\Repository\OrdenRespsRepository;
use App\Repository\OrdenPiezasRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\ScmReceiversRepository;

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
   * Checamos prueba de conexion con retorno sencillo
   */
  #[Route('harbi/check-connx/', methods:['get'])]
  public function checkConnx(): Response
  {
    return $this->json(['abort'=>false, 'body' => 'ok']);
  }

  /**
   * Creamos el schema del centinela nuevo en caso de fracaso via FTP
   */
  #[Route('harbi/build-centinela-schema/', methods:['get'])]
  public function buildSchemaCentinela(CentinelaService $centinela): Response
  {
    $result = $centinela->buildSchemaInitBasic();
    return $this->json(['abort'=>false, 'body' => $result]);
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
    $asigns = $ordAsigns->hasContent();
    // Elementos que no guardan registro en el centinela File.
    $iris = $scm->hasRegsAny();
    $resp = $scm->hasRegsOf('rsp');
    $camping= $scm->hasRegsOf('json');
    $ntg = $filtros->setTheRegsInFileNoTengo();

    $result = [
      'centinela' => !$isSame, 'iris' => $iris, 'resp' => $resp,
      'camping' => $camping, 'ntg' => $ntg, 'asigns' => $asigns
    ];

    return $this->json(['abort'=>false, 'body' => $result]);
  }

  /**
   * 
  */
	#[Route('harbi/get-iris-total/', methods:['get'])]
	public function getRegIris(ScmService $scm, FiltrosService $filtros): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => ['scm' => [], 'ntg' => []]];

    $r['body']['scm'] = $scm->getAll(true);
    $r['body']['ntg'] = $filtros->getAllNoTengo(true);

		return $this->json($r);
	}

  /**
   * Descargamos el contenido del centinela cuando el FTP no funciona
   */
  #[Route('harbi/download-centinela/', methods:['get'])]
  public function downloadCentinela(CentinelaService $centinela): Response
  {
    return $this->json(
      ['abort' => false, 'msg' => 'ok', 'body' => $centinela->downloadCentinela()]
    );
  }
  
  /**
   * Descargamos el contenido del archivo de filtros cuando el FTP no funciona
   */
  #[Route('harbi/download-filtros/', methods:['get'])]
  public function downloadFiltrosFile(FiltrosService $filtros): Response
  {
    return $this->json(
      ['abort' => false, 'msg' => 'ok', 'body' => $filtros->downloadFiltros()]
    );
  }

  /**
   * Descargamos el contenido del archivo de opcotizo (open cotizo), el cual nos indica
   * la apertura de la app, 
   */
  #[Route('harbi/download-opcotizo/', methods:['get'])]
  public function downloadOpCotizoFile(FiltrosService $filtros): Response
  {
    $data = file_get_contents('opcotizo.json');
    return $this->json(
      ['abort' => false, 'msg' => 'ok', 'body' => $data]
    );
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
   * Recuperamos los archivos que representan registros de descargas, vistas y
   * respuestas de los cotizadores ante los mensajes enviados por whatsapp
  */
	#[Route('harbi/get-all-campings/', methods:['get'])]
	public function getAllCampings(ScmService $scm): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $r['body'] = $scm->getAllCampings();
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

  /**
   * Cada cotizador cuando abre una solicitud de cotización actualiza un registro de la
   * tabla scm_receivers, por lo tanto, esos son los ids que vienen en el parametro para
   * poder extraer el id de la orden y el id del avo para enviarles un push.
  */
	#[Route('harbi/get-regs-push-see-byids/{ids}/', methods:['get'])]
	public function getRegsPushSeeByids(ScmReceiversRepository $em, String $ids): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $dql = $em->getRegsPushSeeByids(explode(',', $ids));
    $r['body'] = $dql->getScalarResult();
		return $this->json($r);
	}

  /**
   * Todos los registros de los receptores a los cuales se les ha enviado un msg
   * para una solicitud de cotizacion por medio de whatsapp
  */
	#[Route('harbi/get-regs-receivers-by-id-camp/{id}/', methods:['get'])]
	public function getRegsReceiversByIdCamp(ScmReceiversRepository $em, String $id): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $dql = $em->getRegsReceiversByIdCamp($id);
    $r['body'] = $dql->getScalarResult();
		return $this->json($r);
	}

  /** Recuperamos las respuestas para el centinela de la SCP */
	#[Route('harbi/get-resp-centinela/{idOrd}/', methods:['get'])]
	public function getRespuestaCentinela(OrdenRespsRepository $rpsEm, $idOrd): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    // Recuperamos las respuestas
		$dql = $rpsEm->getRespuestaCentinela($idOrd);
    $r['body'] = $dql->getScalarResult();
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
    $idPasz = [];
    $cent = [];
    $stts = ['est' => '4', 'stt' => '1'];

    if($rota > 0) {
      $r['body'] = $resps;
      for ($i=0; $i < $rota; $i++) { 
        $idPasz[] = $resps[$i]['pieza']['id'];
        $cent[] = ['pieza' => $resps[$i]['pieza']['id'], 'orden' => $resps[$i]['orden']['id']];
      }
    }

    if(count($idPasz) > 0) {
      // Cambiamos el status en la BD
      $pzaEm->changeSttByIdsPiezas($idPasz, $stts);
      // Cambiamos el status en el Centinela
      $centi->setNewSttToPiezasByIds(['piezas' => $cent, 'stts' => $stts, 'version' => $ver]);
    }

		return $this->json($r);
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

  /** */
  #[Route('harbi/get-all-cotz/', methods:['get'])]
  public function getAllCotizadores(NG2ContactosRepository $contactos): Response
  {
    $dql = $contactos->getAllCotizadores(true);
    return $this->json(['abort'=>false,'msg'=>'ok','body'=>$dql->getArrayResult()]);
  }

  /**
   * Recuperamos los datos de una campaña para almacenarlas en archivos locales
   */
  #[Route('harbi/get-camp-for-build/{idOwn}/{idAvo}/{slugCamp}/', methods:['get'])]
  public function getCampForBuild(
    NG2ContactosRepository $contactos, CampaingsRepository $camps,
    int $idOwn, int $idAvo, String $slugCamp): Response
  {
    $result = ['abort' => true, 'msg' => 'err', 'body' => []];
    if($slugCamp == '') {
      return $this->json($result);
    }

    $dql = $camps->getCampaignBySlug($slugCamp);
    $campaing = $dql->getArrayResult();
    $data = [];
    if($campaing) {

      $data['camp'] = $campaing[0]['id'];
			$data['priority']= $campaing[0]['priority'];
			$data['slug']= $campaing[0]['slug'];
			$data['titulo']= $campaing[0]['titulo'];
			$data['despec']= $campaing[0]['despec'];
			$data['msgTxt']= $campaing[0]['msgTxt'];
			$data['isConFilt']= $campaing[0]['isConFilt'];
      $dql = $contactos->getContactById($idOwn);
      $remi = $dql->getArrayResult();
			$data['emiter']  = ($remi) ? $remi[0] : [];;
      $dql = $contactos->getContactById($idAvo, true);
      $remi = $dql->getArrayResult();
			$data['remiter'] = ($remi) ? $remi[0] : [];
    }

    return $this->json(['abort'=>false,'msg'=>'ok','body'=>[$data]]);
  }

  
  /**
   * y liberamos la orden, el prefijo lib es solo para proteccion.
  */
  #[Route('harbi/liberar-ordenes/{idOrden}/', methods: ['get'])]
  public function liberarOrdenes(OrdenesRepository $ordEm, String $idOrden): Response
  {
    $res = ['result' => 'ok', 'msg' => '', 'body' => []];
    if(strpos($idOrden, 'lib') !== false) {

      $idOrden = trim(str_replace('lib-', '', $idOrden));
      $ids = explode(',', $idOrden);
      if(count($ids) > 0) {
        // Cambiamos el status 
        $ordEm->changeSttOrdenTo($ids, ['est' => '3', 'stt' => '2']);
      }
    }

    return $this->json($res);
  }

  /**
   * Enviar notificaciones a los cotizadores cuando una campaña
   * se ha terminado de enviar.
  */
  #[Route('harbi/push-finish-camp/{idOrden}/{idCamp}/{idAvo}/{idsCot}/', methods: ['get'])]
  public function pushFinishCamp(
    NG2ContactosRepository $em, Pushes $push,
    String $idOrden, String $idCamp, String $idAvo, String $idsCot): Response
  {
    $res = ['result' => 'ok', 'unknown' => [], 'invalid' => []];
    if(strpos($idsCot, 'pfc') !== false) {

      $idsCot = trim(str_replace('pfc-', '', $idsCot));
      $ids = explode(',', $idsCot);
      if(count($ids) > 0) {
        $sentTo = $em->getTokensByIds($ids);
        if(count($sentTo) > 0) {
          $res = $push->sendToOwnFinCamp($idOrden, $idCamp, $idAvo, $sentTo);
        }
      }
    }

    return $this->json($res);
  }

  /**
   * Sin uso aun, esta echo para mandar varios tipos de notificaciones segun el parametro
  */
  #[Route('harbi/push-type/{param}/', methods: ['get'])]
  public function pushType(
    NG2ContactosRepository $em, Pushes $push, OrdenesRepository $ordEm,
    String $param): Response
  {
    $res = ['ok Gracias'];
    if(strpos($param, 'scm') !== false) {
      $ids = explode(',', $param);
      if(count($ids) > 0) {
        $sentTo = $em->getTokensByIds($ids);
        if(count($sentTo) > 0) {
          // $res = $push->sendToOwnOfIdType($sentTo);
        }
      }
    }
    return $this->json($res);
  }

}
