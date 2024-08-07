<?php

namespace App\Controller\Cotiza;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;
use App\Service\CotizaService;

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

  #[Route('api/cotiza/set-token-messaging-by-id-user/', methods:['post'])]
  public function setTokenMessaging(NG2ContactosRepository $contacsEm, Request $req): Response
  {
    $data = $this->toArray($req, 'data');
    $contacsEm->safeTokenMessangings($data);
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => []]);
  }

  #[Route('api/cotiza/upload-img/', methods:['post'])]
  public function uploadImg(Request $req, CotizaService $cotService): Response
  {
    $data = $this->toArray($req, 'data');
    $file = $req->files->get($data['campo']);
    
    $result = $cotService->upImgOfOrdenToFolderTmp($data['filename'], $file);
    if(strpos($result, 'rename') !== false) {
      $partes = explode('::', $result);
      $data['filename'] = $partes[1];
      $result = 'ok';
    }
    if($result == 'ok') {
      if(strpos($data['filename'], 'share-') !== false) {
        $cotService->updateFilenameInFileShare($data['idOrden'].'-'.$data['idTmp'], $data['filename']);
      }
    }
    return $this->json([
      'abort' => ($result != 'ok') ? true : false,
      'msg' => '', 'body' => $result
    ]);
  }

  #[Route('api/cotiza/set-file-share-img-device/', methods:['post'])]
  public function setFileShareImgDevice(Request $req, CotizaService $cotService): Response
  {
    $data = $this->toArray($req, 'data');
    $result = $cotService->saveFileSharedImgFromDevices($data);

    return $this->json([
      'abort' => ($result != 'ok') ? true : false,
      'msg' => '', 'body' => $result
    ]);
  }

}
