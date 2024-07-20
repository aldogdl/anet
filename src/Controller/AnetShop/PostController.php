<?php

namespace App\Controller\AnetShop;

use App\Dtos\HeaderDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;
use App\Repository\ProductRepository;
use App\Service\AnetShop\AnetShopSystemFileService;
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

  #[Route('api/anet-shop/is-token-caducado/', methods:['get'])]
	public function isTokenCaducado(): Response
	{
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
	}

  /** */
  #[Route('api/anet-shop/upload-img/', methods:['post'])]
  public function uploadImg(Request $req, AnetShopSystemFileService $sysFile): Response
  {

    $response = ['abort' =>  true];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('id', $data)) {

      $file = $req->files->get('img_' . $data['id']);
      $result = $sysFile->upImgToFolder($data, $file);
      if($result == 'ok') {
        $response['abort'] = false;
      }else{
        $response['body'] = $result;
      }
    }

    return $this->json($response);
  }

  /** 
   * Guardamos el producto enviado desde AnetShop
  */
  #[Route('api/anet-shop/send-product/', methods:['post'])]
	public function sendProduct(
    Request $req, AnetShopSystemFileService $sysFile, WaSender $wh, ProductRepository $emProd
  ): Response
	{

    $result = ['abort' => true];
    try {
      $data = $this->toArray($req, 'data');
    } catch (\Throwable $th) {
      $data = $req->getContent();
      if($data) {
        $data = json_decode($data, true);
      }
    }
    
    $modo = 'solicita';
    if(array_key_exists('meta', $data)) {
      if(array_key_exists('modo', $data['meta'])) {
        $modo = $data['meta']['modo'];
        $modo = ($modo == 'publik') ? 'publica' : $modo;
        $modo = ($modo == 'cotiza') ? 'solicita' : $modo;
      }
    }

    if($modo == 'publik_mlm') {
      // TODO enviar notificacion a BackCore de que una publicacion fue
      // enviada a MLM
      return $this->json($result);
    }

    $id = 0;
    if(array_key_exists('product', $data) && $modo == 'publica') {
      $id = $emProd->setProduct($data['product']);
      if($id == 0) {
        $result['msg']  = 'X No se logró guardar el producto';
        return $this->json($result);
      }else{
        $result['add_product'] = $id;
        $data['product']['id'] = $id;
      }
    }
    
    if(array_key_exists('product', $data) && $modo == 'solicita') {

      $id = $sysFile->setSolicitudInFile($data['product']);
      if(mb_strpos($id, 'X ') !== false) {
        $result['msg']  = $id;
        return $this->json($result);
      }else{
        $result['add_product'] = $id;
        $data['product']['id'] = $id;
      }
    }

    $resort = [];
    if(array_key_exists('resort', $data)) {
      $resort = $data['resort'];
      unset($data['resort']);
    }

    $ownSlug = $data['meta']['slug'];
    $idItem = $data['meta']['id'];
    $filename = $modo.'_'.$ownSlug.'_'.$idItem.'.json';
    $filePath = $sysFile->setItemInFolderSSE($data, $filename);

    if(mb_strpos($filePath, 'X ') === false) {
      
      if(count($resort) > 0) {
        $path = $sysFile->buildPathToImages($modo, $data['meta']['slug']);
        $sysFile->reSortImage($path, $resort);
      }
      
      $sysFile->cleanImgToFolder($data, $modo);
      unset($data['meta']);

      $data['header'] = HeaderDto::event([], $modo);
      $data['header'] = HeaderDto::down($data['header'], true);
      $data['header'] = HeaderDto::fileName($data['header'], $filename);
      $data['header'] = HeaderDto::idItem($data['header'], $idItem);
      $data['header'] = HeaderDto::ownSlug($data['header'], $ownSlug);
      
      $wh->sendMy($data);
      $result['abort'] = false;
      return $this->json($result);
    }

    $result['msg']  = 'X Error al guardar producto';
	  return $this->json($result);
	}

  /** 
   * Eliminamos la pieza
  */
  #[Route('api/anet-shop/delete-product/', methods:['POST'])]
	public function deleteSolicitud(Request $req, AnetShopSystemFileService $sysFile, WaSender $wh): Response
	{
    $result = ['abort' => true, 'body' => 'Error desconocido'];
    $data = $this->toArray($req, 'data');
    $res = $sysFile->deleteSolicitud($data);
    $result['body'] = $res;
    if($res == 'ok') {
      $result['abort'] = false;
      
      $data['header'] = HeaderDto::event([], 'delete_pza');
      $data['header'] = HeaderDto::idItem($data['header'], $data['uuid']);
      $wh->sendMy($data);
    }

    return $this->json($result);
  }

  /** 
   * Marcamos este producto como ?? desde AnetShop y enviamos aviso a BackCore
  */
  #[Route('api/anet-shop/update-stt-product/', methods:['post'])]
	public function sendedProductToMlm(Request $req, WaSender $wh, ProductRepository $emProd): Response
	{
    $result = ['abort' => true];
    $data = $this->toArray($req, 'data');
    $changed = $emProd->updateStatusProduct($data);
    
    if($changed == 'ok') {

      $result['abort'] = false;
      $data['header'] = HeaderDto::event([], 'product_stt');
      $wh->sendMy($data);
      
    }else{
      $result['msg'] = 'Error';
      $result['body'] = $changed;
    }

    $result['detas'] = file_get_contents('desc_mlm.txt');
	  return $this->json($result);
	}

  /** */
  #[Route('security-basic/mark-product-as/{token}/', methods:['post'])]
	public function markProductAs(
    Request $req, SecurityBasic $lock, AnetShopSystemFileService $sysFile, String $token
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
    Request $req, SecurityBasic $lock, AnetShopSystemFileService $sysFile, String $token
  ): Response
	{
    $data = [];
    if($lock->isValid($token)) {
      
      $payload = $this->toArray($req, 'data');
      $data = $sysFile->saveComments($payload);
    }
	  return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
	}

  /** 
   * Recibimos los errores que se generan en la app AnetShop
  */
  #[Route('security-basic/log/errs/', methods:['post'])]
	public function setLogErrs(
    Request $req, SecurityBasic $lock, AnetShopSystemFileService $sysFile, String $token
  ): Response
	{
    $data = ['abort'=>true, 'msg' => 'err'];
    if($lock->isValid($token)) {
      $payload = $this->toArray($req, 'data');
      $data = $sysFile->saveLogError($payload);
    }
	  return $this->json($data);
	}

}
