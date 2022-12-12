<?php

namespace App\Controller\Cotizo;

use App\Repository\FiltrosRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Repository\OrdenRespsRepository;
use App\Service\CotizaService;
use App\Service\FiltrosService;
use App\Service\ScmService;

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

  #[Route('cotizo/set-token-messaging-by-id-user/', methods:['post'])]
  public function setTokenMessaging(NG2ContactosRepository $contacsEm, Request $req): Response
  {
    $data = $this->toArray($req, 'data');
    $contacsEm->safeTokenMessangings($data);
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => []]);
  }

  #[Route('cotizo/upload-img/', methods:['post'])]
  public function uploadImg(Request $req, CotizaService $cotService): Response
  {
    $data = $this->toArray($req, 'data');
    $file = $req->files->get($data['campo']);

    $result = $cotService->upImgOfRespuestaToOrden($data['filename'], $file);
    return $this->json([
      'abort' => ($result != 'ok') ? true : false,
      'msg' => '', 'body' => $result
    ]);
  }

  /** Guardamos la respuesta del cotizador */
  #[Route('cotizo/set-resp/', methods:['post'])]
  public function setRespuesta(
    Request $req, OrdenRespsRepository $rpsEm, ScmService $scm
  ): Response
  {
    $data = $this->toArray($req, 'data');
    
    $result = $rpsEm->setRespuesta($data);

    if(!$result['abort']) {
      if(!array_key_exists('fromLocal', $data)) {
        $fileName = $data['idOrden'].'-'. $data['own'].'-'. $data['idPieza'];
        $scm->setNewRegType($fileName.'-'.$result['body'].'.rsp', $data);
      }
    }
    
    return $this->json($result);
  }

  /**
   * Buscamos una nueva carnada para el cotizador para que no salga del estanque
   * A su ves:
   * B) Creamos el archivo de visto.
  */
  #[Route('cotizo/fetch-carnada/', methods:['post'])]
  public function fetchCarnada(
    Request $req, OrdenesRepository $ordEm, FiltrosService $filts
  ): Response
  {
    $ansuelo = $this->toArray($req, 'data');
    // Recuperamos las no tengo de usuario para filtrar el resultado de la carnaada
    $ntgo = $filts->getNtnByIdCot($ansuelo['ct']);

    // Buscamos una orden que conicida con el ansuelo
    $res = $ordEm->fetchCarnadaByAnsuelo($ansuelo, $ntgo);
    return $this->json(['abort' => false, 'body' => $res]);
  }
  
  /**
   * Buscamos las ordenes y sus piezas que el usuario halla apartado
  */
  #[Route('cotizo/get-piezas-apartadas/', methods:['post'])]
  public function getPzasApartadas( Request $req, OrdenesRepository $ordEm): Response
  {
    $data = $this->toArray($req, 'data');
    $res  = [];
    if(array_key_exists('ap', $data)) {
      $dql = $ordEm->getOrdenesAndPiezasApartadas($data['ap']);
      $res = $dql->getArrayResult();
    }
    return $this->json(['abort' => false, 'body' => $res]);
  }
  
  /**
   * REVISAR NO IMPLEMENTADO
   * C) Guardamos el filtro de que maneja esta marca.
  */
  #[Route('cotizo/grabar-filtro/', methods:['post'])]
  public function grabarFiltro(
    Request $req, FiltrosRepository $filEm, NG2ContactosRepository $contacEm
  ): Response
  {
    
    $data = $this->toArray($req, 'data');
    $res = [];

    // Grabar filtros
    if($data['setF']) {
      $idEmp = $contacEm->getIdEmpresaByIdContacto($data['ct']);
      if($idEmp != 0) {

        $save = true;
        if(!array_key_exists('mk', $data['at'])) {
          if(!array_key_exists('idOrdCurrent', $data['at'])) {
            $save = false;
          }
        }

        if($save) {
          $dataFilter = [
            'emp' => $idEmp, 'marca' => $data['at']['mk'], 'grupo' => 't'
          ];
          $filEm->setFiltro($dataFilter, $idEmp);
        }
      }
    }

    return $this->json(['abort' => false, 'body' => $res]);
  }

}
