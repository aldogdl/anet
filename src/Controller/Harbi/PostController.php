<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AutosRegRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Repository\OrdenRespsRepository;
use App\Service\HarbiConnxService;
use App\Service\ScmService;
use App\Service\StatusRutas;

class PostController extends AbstractController
{
	/**
	 * Obtenemos el request contenido decodificado como array
	 *
	 * @throws JsonException When the body cannot be decoded to an array
	 */
	public function toArray(Request $req, String $campo): array
	{
		$content = $req->request->get($campo);
		try {
			$content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new JsonException('No se puede decodificar el body.', $e->getCode(), $e);
		}
		if (!\is_array($content)) {
			throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
		}
		return $content;
	}

	#[Route('harbi/save-ip-address-harbi/', methods:['post'])]
	public function saveIpAdressHarbi(Request $req, HarbiConnxService $harbi): Response
	{   
		$data = $this->toArray($req, 'data');
		$harbi->saveIp($data);
		return $this->json(['abort'=>false, 'msg' => 'ok','body' => 'save']);
	}
	
	#[Route('harbi/save-ruta-last/', methods:['post'])]
	public function saveRutaLast(Request $req, StatusRutas $rutas): Response
	{
		$data = $this->toArray($req, 'data');
		$rutas->setNewRuta($data);
		return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => 'save']);
	}

	#[Route('harbi/set-orden/', methods:['post'])]
	public function setOrden(
	  Request $req, OrdenesRepository $ordEm, AutosRegRepository $autoEm
	): Response
	{
	  $data = $this->toArray($req, 'data');
	  $autoEm->regAuto($data);
	  $result = $ordEm->setOrden($data);
	  return $this->json($result);
	}
	
  #[Route('harbi/set-pieza/', methods:['post'])]
  public function setPieza(
    Request $req, OrdenPiezasRepository $pzasEm,
  ): Response
  {
    $data = $this->toArray($req, 'data');
    $result = $pzasEm->setPieza($data);
    return $this->json($result);
  }

  /**
   * Guardamos la respuesta del cotizador
   * Generalmente usado para guardar los datos en local
   */
  #[Route('harbi/set-resp/', methods:['post'])]
  public function setRespuesta(Request $req, OrdenRespsRepository $rpsEm): Response
  {
    $data = $this->toArray($req, 'data');
    $result = $rpsEm->setRespuesta($data, true);
    return $this->json($result);
  }

	
  /**
   * Registramos para la SCM una campaña
   */
  #[Route('harbi/set-new-campaing/', methods:['post'])]
  public function setNewCampaing(Request $req, ScmService $scmServ): Response
  {
    $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('slug_camp', $data)) {
      $scmServ->setNewMsg($data);
      $result['abort'] = false;
      $result['body'] = 'ok';
    }
    return $this->json($result);
  }

  // /**
  //  * Pendiente tal ves borrar
  //  */
  // #[Route('harbi/set-reg-campaing-scm/', methods:['post'])]
  // public function setNewCampaingMsg(
  //   Request $req, ScmCampRepository $scmEm, CampaingsRepository $camps,
  //   OrdenesRepository $ordsEm, OrdenPiezasRepository $pzasEm,
  //   CentinelaService $centinela, ScmService $scmServ
  // ): Response
  // {
  //   $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
  //   $data = $this->toArray($req, 'data');
  //   if(!array_key_exists('camp', $data)) {
  //     return $this->json($result);
  //   }
    
  //   $dql = $camps->getCampaignBySlug($data['camp']['slug_camp']);
  //   $campaing = $dql->getArrayResult();

  //   if($campaing) {

  //     $data['camp']['camp'] = $campaing[0]['id'];
  //     $result = $scmEm->setNewCampaing($data['camp']);

  //     if(!$result['abort']) {

  //       if($data['camp']['target'] == 'orden') {
  //         $ordsEm->changeSttOrdenTo($data['camp']['src']['id'], $data['ordS']);
  //         $data['ordS']['orden'] = $data['camp']['src']['id'];
  //         $data['ordS']['version'] = 0;
  //         $isOk = $centinela->setNewSttToOrden($data['ordS']);
  //         if($isOk) {
  //           $pzasEm->changeSttPiezasTo($data['camp']['src']['id'], $data['pzS']);
  //           $data['pzS']['orden'] = $data['camp']['src']['id'];
  //           $data['pzS']['version'] = $data['verC'];
  //           $scmServ->setNewMsg($result['body']);
  //           $isOk = $centinela->setNewSttToPiezas($data['pzS']);
  //           if(!$isOk) {
  //             $result['body'] = 'Error registrando status Piezas en centinela.';
  //           }
  //         }else{
  //           $result['body'] = 'Error registrando status Orden en centinela.';
  //         }
          
  //         if($isOk) {
  //           $result['abort']= false;
  //           $result['msg']  = 'ok';
  //         }
  //       }
  //       return $this->json($result);
  //     }
  //   }else{
  //     $result['body'] = 'No se encontró la campaña '.$data['camp']['slug_camp'];
  //   }

  //   return $this->json($result);
  // }
}
