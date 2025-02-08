<?php

namespace App\Controller\RfyForm;

use App\Repository\FcmRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;
use App\Repository\ItemsRepository;
use App\Service\ItemTrack\WaSender;
use App\Service\HeaderItem;
use App\Service\Pushes;

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
   * Guardamos el token FCM
  */
  #[Route('rfyform/tkfcm/{key}', methods:['POST'])]
	public function setTokenFCM(Request $req, SecurityBasic $sec, FcmRepository $fcmEm, WaSender $waS, String $key): Response
	{
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    $result = ['abort' => true, 'msg' => ''];
    $data = [];
    try {
      $data = $this->toArray($req, 'data');
    } catch (\Throwable $th) {
      $data = $req->getContent();
      if($data) {
        $data = json_decode($data, true);
      }else{
        $result['msg']  = 'X No se logró decodificar correctamente los datos de la request.';
        return $this->json($result);
      }
    }
    
    // Las metas es informacion que cada dispoitivo usado por el usuario nos envia
    // para conocer sus caracteristicas con finalidad de ofrecer un mejor soporte.
    if(array_key_exists('meta', $data)) {
      $folderMetas = $this->getParameter('sseMetas');
      $filename = $data['slug'].'_'.$data['waId'].'.json';
      file_put_contents($folderMetas.'/'.$filename, json_encode($data['meta']));
      unset($data['meta']);
    }
    
    $res = $fcmEm->setDataToken($data);
    $result['msg'] = $res;
    if(strpos($res, 'X') === false) {
      $result['abort'] = false;
      if(array_key_exists('isInit', $data) && $data['isInit'] != 'debug') {
        // TODO
        $waS->sendMy([]);
      }
    }

    return $this->json($result);
  }

  /** 
   * Enviamos la notificacion de nueva publicacion a los contactos
  */
  #[Route('rfyform/make_push/{key}', methods:['POST'])]
	public function sentNotification(
    Request $req, SecurityBasic $sec, FcmRepository $fcmEm, WaSender $waS, Pushes $push, String $key
  ): Response
	{
    // if(!$sec->isValid($key)) {
    //   $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
    //   return $this->json($result);
    // }

    $result = ['abort' => true, 'msg' => ''];
    $data = [];
    try {
      $data = $this->toArray($req, 'data');
    } catch (\Throwable $th) {
      $data = $req->getContent();
      if($data) {
        $data = json_decode($data, true);
      }else{
        $result['msg']  = 'X No se logró decodificar correctamente los datos de la request.';
        return $this->json($result);
      }
    }

    $contacts = $fcmEm->getContactsForSend($data);
    if(count($contacts) == 0) {
      $result = ['abort' => true, 'msg' => 'X Sin contactos'];
    }else{
      
      $filename = $this->getParameter('fbSended') .
      $data['type'] .'_'. round(microtime(true) * 1000) . '.json';
      
      if(array_key_exists('slug', $contacts)) {
        $data['srcSlug'] = $contacts['slug'];
        file_put_contents($filename, json_encode($data));
        $data['tokens'] = $contacts['tokens'];
      }else{
        file_put_contents($filename, json_encode($data));
        $data['tokens'] = $contacts;
      }
      
      $data['cant'] = count($data['tokens']);
      file_put_contents($filename, json_encode($data));

      $result = $push->sendMultiple($data);
      if(array_key_exists('fails', $result)) {
        $filename = $this->getParameter('fbFails') .
          $data['type'] .'_'. round(microtime(true) * 1000) . '.json';
        file_put_contents($filename, json_encode($data));
        unset($result['fails']);
      }
    }

    return $this->json($result);
  }

  /**
   * Guardamos el item enviado desde RasForm
  */
  #[Route('rfyform/item/{key}', methods:['POST'])]
	public function sendProduct(
    Request $req, WaSender $wh, SecurityBasic $sec, ItemsRepository $itemEm,
    FcmRepository $fbem, Pushes $push, String $key
  ): Response
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

    $id = $itemEm->setItem($data);
    if($id == 0) {
      $result['msg']  = 'X No se logró guardar el producto en D.B.';
      return $this->json($result);
    }
    $data['id']        = $id;
    $data['source']    = 'form';
    $data['checkinSR'] = date("Y-m-d\TH:i:s.v");

    if(!$isDebug) {
      $builder = new HeaderItem();
      $head = $builder->build($data);
      $wh->sendMy($head);
    }

    $result['abort']   = false;
    $result['idDbSr']  = $id;
    $result['idItem']  = $data['idItem'];
    $result['isDebug'] = $isDebug;
    return $this->json($result);

	}

}
