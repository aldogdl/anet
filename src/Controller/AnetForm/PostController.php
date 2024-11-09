<?php

namespace App\Controller\AnetForm;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;
use App\Dtos\HeaderDto;
use App\Repository\ItemsRepository;
use App\Service\AnetTrack\WaSender;

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
   * Guardamos el item enviado desde AnetFrom
  */
  #[Route('anet-form/item/{key}', methods:['GET', 'POST', 'DELETE'])]
	public function sendProduct(Request $req, WaSender $wh, SecurityBasic $sec, ItemsRepository $itemEm, String $key): Response
	{

    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'Permiso denegado'];
      return $this->json($result);
    }

    $result = ['abort' => true, 'msg' => ''];
    try {
      $data = $this->toArray($req, 'datos');
    } catch (\Throwable $th) {
      $data = $req->getContent();
      if($data) {
        $data = json_decode($data, true);
      }
    }

    $isDebug = (array_key_exists('debug', $data)) ? true : false;

    $id = $itemEm->setProduct($data);
    if($id == 0) {
      $result['msg']  = 'X No se logró guardar el producto';
    }else{
      $result['anet_id'] = $id;
      $data['id'] = $id;
    }

    if(mb_strpos($result['msg'], 'X ') === false) {

      $type = $data['type'];
      $slug = $data['ownSlug'];
      $idItem = $data['idItem'];

      // Solucion temporal, convertidor del Item nuevo a un Json para el sistema ComCore
      $data = $itemEm->parseItem($data);
      
      $data['header'] = HeaderDto::event([], $type);
      $data['header'] = HeaderDto::includeBody($data['header'], false);
      $data['header'] = HeaderDto::idItem($data['header'], $idItem);
      $data['header'] = HeaderDto::ownSlug($data['header'], $slug);
      $data['header'] = HeaderDto::source($data['header'], 'anet_shop');

      if(!$isDebug) {
        file_put_contents('a_prueba.json', json_encode($data));
        $wh->sendMy($data);
      }
      $result['abort'] = false;
      $result['idItem'] = $idItem;
      $result['isDebug'] = $isDebug;
      return $this->json($result);
    }

    $result['msg']  = 'X Error al guardar producto';
	  return $this->json($result);
	}

}
