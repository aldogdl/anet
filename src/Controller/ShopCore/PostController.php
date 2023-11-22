<?php

namespace App\Controller\ShopCore;

use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\WebHook;
use App\Service\SecurityBasic;
use App\Service\ShopCore\ShopCoreSystemFileService;


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

  #[Route('api/shop-core/is-token-caducado/', methods:['get'])]
	public function isTokenCaducado(): Response
	{
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
	}

  /** */
  #[Route('api/shop-core/upload-img/', methods:['post'])]
  public function uploadImg(Request $req, ShopCoreSystemFileService $sysFile): Response
  {

    $response = ['abort' =>  true];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('id', $data)) {

      $file = $req->files->get('img_' . $data['id']);
      $result = $sysFile->upImgToFolder($data, $file);
      if($result == 'ok') {
        $response['abort'] = false;
      }
    }

    return $this->json($response);
  }

  /** 
   * Guardamos el producto enviado desde ShopCore
  */
  #[Route('api/shop-core/send-product/', methods:['post'])]
	public function sendProduct(
    Request $req, ShopCoreSystemFileService $sysFile, WebHook $wh, ProductRepository $emProd
  ): Response
	{

    $result = ['abort' => true];
    $data = $this->toArray($req, 'data');
    
    $modo = 'add';
    if(array_key_exists('meta', $data)) {
      if(array_key_exists('modo', $data['meta'])) {
        $modo = $data['meta']['modo'];
      }
    }

    if($modo == 'publik_mlm') {
      // TODO enviar notificacion a BackCore de que una publicacion fue
      // enviada a MLM
      return $this->json($result);
    }

    $id = 0;
    if(array_key_exists('product', $data)) {
      $id = $emProd->setProduct($data['product']);
      $result['add_product'] = $id;
      $data['id'] = $id;
    }

    $filename = $data['meta']['modo'].'_'.$data['meta']['slug'].'_'.$data['id'].'.json';
    $filePath = $sysFile->setNewProduct($data, $filename);

    if(mb_strpos($filePath, 'Error') === false) {
      
      if($filePath != '') {

        $ftoFalta = $sysFile->checkExistAllFotos($data);
        $result['abort'] = false;
        if(count($ftoFalta) > 0) {
          $result['faltan_fotos'] = $ftoFalta;
        }
        
        try {
          $wh->sendMy('api\\shop-core\\send-product', $filePath, $data);
        } catch (\Throwable $th) {
          $result['sin_wh'] = $th->getMessage();
        }
        return $this->json($result);
      }
    }

    $result['msg']  = $filePath;
	  return $this->json($result);
	}

  /** 
   * Marcamos este producto como ?? desde AnetShop y enviamos aviso a BackCore
  */
  #[Route('api/anet-shop/update-stt-product/', methods:['post'])]
	public function sendedProductToMlm(Request $req, WebHook $wh, ProductRepository $emProd): Response
	{
    $result = ['abort' => true];
    $data = $this->toArray($req, 'data');
    file_get_contents('lo_recibido.json', json_encode($data));
    $changed = $emProd->updateStatusProduct($data);
    
    if($changed == 'ok') {
      $result['abort'] = false;
      try {
        $wh->sendMy('api\\anet-shop\\send-product-mlm', '', $data);
      } catch (\Throwable $th) {
        $result['sin_wh'] = $th->getMessage();
      }
    }else{
      $result['msg'] = 'Error';
      $result['body'] = $changed;
    }
    file_get_contents('lo_recibido_resp.json', json_encode($result));
    
	  return $this->json($result);
	}

  /** */
  #[Route('security-basic/mark-product-as/{token}/', methods:['post'])]
	public function markProductAs(
    Request $req, SecurityBasic $lock, ShopCoreSystemFileService $sysFile, String $token
  ): Response
	{
    $data = [];
    if($lock->isValid($token)) {
      
      $payload = $this->toArray($req, 'data');
      $data = $sysFile->markProductAs($payload);
    }
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
	}

  /** 
   * Recibimos los comentarios y sugerencias sobre el uso de la app
  */
  #[Route('security-basic/send-comments/{token}/', methods:['post'])]
	public function sendComments(
    Request $req, SecurityBasic $lock, ShopCoreSystemFileService $sysFile, String $token
  ): Response
	{
    $data = [];
    if($lock->isValid($token)) {
      
      $payload = $this->toArray($req, 'data');
      $data = $sysFile->saveComments($payload);
    }
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
	}

}
