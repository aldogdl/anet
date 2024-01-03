<?php

namespace App\Controller\EventCore;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\WebHook;
use App\Service\AnetShop\AnetShopSystemFileService;
use App\Service\EventCore\EventCoreSystemFileService;

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
      throw new JsonException(sprintf('No se puede decodificar el body, "%s".', get_debug_type($content)));
    }

    if (!\is_array($content)) {
      throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
    }
    return $content;
  }

  /** 
   * Guardamos el producto enviado desde AnetShop
  */
  #[Route('api/event-core/save-product/', methods:['post'])]
	public function saveProduct(
    Request $req, AnetShopSystemFileService $sysFile, WebHook $wh
  ): Response
	{
    $result = ['abort' => true];
    $data = $this->toArray($req, 'data');
        
    if(array_key_exists('product', $data)) {
      // TODO Aqui me quede, es necesario hacer otra clase igual a AnetShopSystemFileService
      // para EventCore...
      $id = $sysFile->setSolicitudInFile($data);
      if(mb_strpos($id, 'X ') !== false) {
        $result['msg']  = $id;
        $result['body'] = 'X Error al guardar producto';
        return $this->json($result);
      }else{
        $result['add_product'] = $id;
      }
    }
    
	  return $this->json($result);
	}

  /** 
   * Guardar el mensaje prefabricado del rastreo de una solicitud
  */
  #[Route('api/event-core/save-prod-track/', methods:['post'])]
	public function saveProdTrack(Request $req, EventCoreSystemFileService $sysFile): Response
	{
    $result = ['abort' => false];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('id', $data)) {

      $res = $sysFile->setProdTrack($data);
      if(mb_strpos($res, 'X ') !== false) {
        $result['abort']  = true;
        $result['msg']  = 'error';
      }
      $result['body'] = $res;
    }

	  return $this->json($result);
	}

}
