<?php

namespace App\Controller\SCP;

use App\Entity\OrdenPiezas;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Service\ScmService;
use App\Service\CentinelaService;
use App\Service\OrdenesAsigns;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CentinelaController extends AbstractController
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

  /** */
  #[Route('scp/centinela/ordenes-asignadas/', methods:['post'])]
  public function seveDataContact(
    Request $req, CentinelaService $centinela,
    OrdenesRepository $ordenes, OrdenesAsigns $ordAsigns
  ): Response
  {
    $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
    $data = $this->toArray($req, 'data');
    
    if(array_key_exists('info', $data)) {
      foreach ($data['info'] as $idAvo => $ords) {
        $result = $ordenes->asignarOrdenesToAvo((integer) $idAvo, $ords);
        if($result['abort']) { break; }
      }
      if(!$result['abort']) {
        $centinela->asignarOrdenes($data);
        if(!$data['isLoc']) {
          $ordAsigns->setNewOrdAsigns(''.$data['version']);
        }
      }
    }
    return $this->json($result);
  }

  /** */
  #[Route('scp/centinela/change-stt-to-orden/', methods:['post'])]
  public function changeSttToOrden(
    Request $req, OrdenesRepository $ordsEm, CentinelaService $centinela
  ): Response
  {
    $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
    $data = $this->toArray($req, 'data');
    $ordsEm->changeSttOrdenTo($data['orden'], $data);
    $isOk = $centinela->setNewSttToOrden($data);
    if($isOk) {
      $result['abort']= false;
      $result['msg']  = 'ok';
      $result['body'] = 'ok';
    }
      // ToDoPush
      // hacer una notificación push al solicitante del cambio de estatus de la orden
    return $this->json($result);
  }

}
