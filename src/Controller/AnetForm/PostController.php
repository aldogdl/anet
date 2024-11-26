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
use App\Service\HeaderItem;

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
   * Guardamos el item enviado desde AnetForm
  */
  #[Route('anet-form/item/{key}', methods:['POST'])]
	public function sendProduct(Request $req, WaSender $wh, SecurityBasic $sec, ItemsRepository $itemEm, String $key): Response
	{

    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    $result = ['abort' => true, 'msg' => ''];
    $data = [];
    try {
      $data = $this->toArray($req, 'datos');
    } catch (\Throwable $th) {
      $data = $req->getContent();
      if($data) {
        $data = json_decode($data, true);
      }else{
        $result['msg']  = 'X No se logró decodificar correctamente los datos de la request.';
        return $this->json($result);
      }
    }

    // Esto es usado para que no se envie el evento hacia el puente y ComCore no
    // reciba esta prueba, la misma que se realiza desde AnetForm
    $isDebug = (array_key_exists('debug', $data)) ? true : false;

    $id = $itemEm->setProduct($data);
    if($id == 0) {
      $result['msg']  = 'X No se logró guardar el producto en D.B.';
      return $this->json($result);
    }
    $data['id']        = $id;
    $data['source']    = 'anet_form';
    $data['checkinSR'] = date("Y-m-d\TH:i:s.v");
    $builder = new HeaderItem();
    $head = $builder->build($data);
    
    if(!$isDebug) {
      $wh->sendMy($head);
    }

    $result['abort']   = false;
    $result['anet_id'] = $id;
    $result['idItem']  = $data['idItem'];
    $result['isDebug'] = $isDebug;
    return $this->json($result);

	}

}
