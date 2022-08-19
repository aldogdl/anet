<?php

namespace App\Controller\Cotizo;

use App\Repository\AutosRegRepository;

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

  #[Route('cotizo/set-token-messaging-by-id-user', methods:['post'])]
  public function setTokenMessaging(NG2ContactosRepository $contacsEm, Request $req): Response
  {
    $data = $this->toArray($req, 'data');
    $contacsEm->safeTokenMessangings($data);
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => []]);
  }

  #[Route('cotizo/upload-img', methods:['post'])]
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
  #[Route('cotizo/set-resp', methods:['post'])]
  public function setRespuesta(
    Request $req, OrdenRespsRepository $rpsEm, ScmService $scm
  ): Response
  {
    $data = $this->toArray($req, 'data');
    $result = $rpsEm->setRespuesta($data);

    if(!$result['abort']) {
      if(!array_key_exists('fromLocal', $data)) {
        if(array_key_exists('idsFromLink', $data)) {
          // Estas son respondidas desde el link del WhatsApp, cuentan con campaña
          $fileName = $result['body'] .'-'. $data['idsFromLink'];
        }else{
          // Estas son respondidas desde la pagina de home de la app. por lo tanto no tienen campaña
          $fileName = $result['body'] . '-cam';
        }
        $scm->setNewRegType($fileName.'-'.$data['id'].'.rsp');
      }
    }
    
    return $this->json($result);
  }

}
