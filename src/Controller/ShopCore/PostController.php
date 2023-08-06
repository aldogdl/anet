<?php

namespace App\Controller\ShopCore;

use App\Service\ShopCore\ShopCoreSystemFileService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

  #[Route('api/shop-core/is-token-caducado/', methods:['get'])]
	public function isTokenCaducado(): Response
	{
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
	}

  /** */
  #[Route('api/shop-core/upload-img/', methods:['post'])]
  public function uploadImg(Request $req, ShopCoreSystemFileService $sysFile): Response
  {
    $data = $this->toArray($req, 'data');
    $file = $req->files->get($data['campo']);
    
    $result = $sysFile->upImgOfOrdenToFolderTmp($data['filename'], $file);
    if(strpos($result, 'rename') !== false) {
      $partes = explode('::', $result);
      $data['filename'] = $partes[1];
      $result = 'ok';
    }

    return $this->json([
      'abort' => ($result != 'ok') ? true : false,
      'msg' => '', 'body' => $result
    ]);
  }

}
