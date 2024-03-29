<?php

namespace App\Controller\SCP\Solicitudes;

use App\Repository\OrdenPiezasRepository;
use App\Service\CotizaService;
use App\Service\ScmService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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

  /**
   * Editamos desde la SCP los datos de la refaccion que se esta checando
   */
  #[Route('scp/solicitudes/editar-data-pieza/', methods:['post'])]
  public function editarDataPieza(
    Request $req, OrdenPiezasRepository $pzaEm, CotizaService $cotService
  ): Response
  {
    $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
    $data = $this->toArray($req, 'data');

    $result = $pzaEm->setPieza($data);
    if(!$result['abort']) {
      // Eliminamos las fotos que han sido indicadas.
      if(array_key_exists('fotosD', $data)) {
        $has = count($data['fotosD']);
        if($has > 0) {
          if($data['pathF'] == 'to_orden_tmp') {
            for ($i=0; $i < $has; $i++) {
              $cotService->removeImgOfOrdenToFolderTmp($data['fotosD'][$i]);
            }
          }
        }
      }
    }
    return $this->json($result);
  }

  /**
   * Registramos para la SCM una campaña
   */
  #[Route('scp/solicitudes/set-reg-campaing-scm/', methods:['post'])]
  public function setNewCampaing( Request $req, ScmService $scmServ): Response
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

}
