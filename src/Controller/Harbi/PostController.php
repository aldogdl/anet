<?php

namespace App\Controller\Harbi;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AutosRegRepository;
use App\Repository\CampaingsRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Repository\OrdenRespsRepository;
use App\Repository\ScmCampRepository;
use App\Service\CentinelaService;
use App\Service\HarbiConnxService;
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
    $isLocal = false;
    if(array_key_exists('fromLocal', $data)) {
      $isLocal = $data['fromLocal'];
    }
    $result = $rpsEm->setRespuesta($data, $isLocal);
    return $this->json($result);
  }
  
  /**
   * Cambiamos stt de ordenes y sus piezas en SR
   */
  #[Route('harbi/set-ests-stts/', methods:['post'])]
  public function setEstsStts(
    Request $req, CentinelaService $centi,
    OrdenesRepository $ordsEm, OrdenPiezasRepository $pzasEm,
  ): Response
  {
    $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
    
		$data = $this->toArray($req, 'data');
    if(array_key_exists('ordenes', $data)) {
      $ordenes = $data['ordenes'];
      if(array_key_exists('version', $data)) {
        $ver  = $data['version'];
        $data = [];
        $rota = count($ordenes);
        if($rota > 0) {
          
          for ($i=0; $i < $rota; $i++) {
            if(array_key_exists('orden', $ordenes[$i])) {
              $ids = [];
              if(!is_array($ordenes[$i]['orden'])) {
                $ids = [$ordenes[$i]['orden']];
              }else{
                $ids = $ordenes[$i]['orden'];
              }
              $ordsEm->changeSttOrdenTo($ids, $ordenes[$i]['stt']);
              $pzasEm->changeSttPiezasTo($ordenes[$i]['orden'], $ordenes[$i]['stt']);
            }
          }
          
          if($ver != 'none') {
            $centi->setEstSttFromArray($ordenes, $ver);
          }
        }

      }
    }	

    return $this->json(['abort' => false, 'msg' => 'ok', 'body' => '']);
  }

  /** */
  #[Route('harbi/upload-img-thumb/', methods:['post'])]
  public function uploadImg(Request $req): Response
  {
    $image = $req->request->get('image');
    $filename = $req->request->get('filename');
    $pathTo = $this->getParameter('imgOrdTmp');
    $filehandler = fopen($pathTo.'/'.$filename, 'wb'); 
    fwrite($filehandler, base64_decode($image));
    $result = fclose($filehandler); 
	
    return $this->json([
      'abort' => ($result === false) ? true : false, 'msg' => 'ok', 'body' => $result/1000
    ]);
  }

}
