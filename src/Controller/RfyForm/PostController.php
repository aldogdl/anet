<?php

namespace App\Controller\RfyForm;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\FcmRepository;
use App\Service\SecurityBasic;
use App\Repository\ItemsRepository;
use App\Service\ItemTrack\WaSender;
use App\Service\HeaderItem;
use App\Service\MyFsys;
use App\Service\Pushes;
use App\Service\RasterHub\TrackProv;
use App\Service\SincronizerItem;

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
  * Desde la app checamos si hay conexion al servidor
  * aprovechando para guardar los metadatos del dispositivo
  */
  #[Route('rfyform/test-connection/{key}', methods:['POST'])]
	public function testConection(Request $req, SecurityBasic $sec, MyFsys $fsys, String $key): Response
  {
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    // Las metas es informacion que cada dispoitivo usado por el usuario nos envia
    // para conocer sus caracteristicas con finalidad de ofrecer un mejor soporte.
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

    if(array_key_exists('meta', $data)) {
      $folderMetas = $this->getParameter('sseMetas');
      if(array_key_exists('dev_full', $data['meta'])) {
        $filename = $data['slug'].'_'.$data['meta']['dev_full'].'_'.$data['waId'].'.json';
      }else{
        $filename = $data['slug'].'_'.$data['waId'].'.json';
      }
      file_put_contents($folderMetas.'/'.$filename, json_encode($data['meta']));
    }
    
    $dic = $fsys->getContent('appData', 'dicc.json');
    $mmc = $fsys->getContent('appData', 'brands_rfy.json');
    return $this->json(['abort' => false, 'dic' => $dic, 'mmc' => $mmc, 'msg' => 'ok']);
  }

  /**
   * Guardamos el token de Whats desde app
  */
  #[Route('rfyform/tkwapi/{key}', methods:['POST'])]
	public function setTokenWapi(Request $req, SecurityBasic $sec, MyFsys $fsys, String $key): Response
  {
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    $data = [];
    $result = ['abort' => true, 'msg' => 'X Sin data'];
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

    $result = $fsys->updateTokenWapi($data['token']);
    return $this->json($result);
  }

  /** 
   * Guardamos el token FCM
  */
  #[Route('rfyform/tkfcm/{key}', methods:['POST'])]
	public function setTokenFCM(Request $req, SecurityBasic $sec, FcmRepository $fcmEm, Pushes $push, String $key): Response
	{
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

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

    // [SUBSCRIPCION]
    // En caso de que el dispositivo sea web, subscribimos al usuario a los temas correspondientes.
    // ya que en la web no se pueden subscribir los clientes a temas.
    if(array_key_exists('device', $data)) {
      if(mb_strpos($data['device'], 'web') !== false) {
        $res = $push->subcriptToTopics($data);
        if(!$res['abort']) {
          if(array_key_exists('buscar', $res)) {
            $data['buscar'] = $res['buscar'];
          }
          if(array_key_exists('vender', $res)) {
            $data['vender'] = $res['vender'];
          }
        }
      }
    }
    
    // Guardamos la marca de login en la BD de FB
    $result = $fcmEm->setLoggedFromApp($data);
    return $this->json($result);
  }

  /** 
  * Este controlador revisa la subscripción de un token a un determinado tema
  */
  #[Route('rfyform/subs-topic/{key}', methods:['POST'])]
	public function checkSubsTopic(Request $req, SecurityBasic $sec, Pushes $push, String $key): Response
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

    try {
      $res = $push->isSubscripted($data['token']);
      $result['abort'] = false;
      $result['body'] = $res;
    } catch (\Throwable $th) {
      $result['msg'] = $th->getMessage();
    }

    return $this->json($result);
  }

  /** 
   * Guardamos la info de ntga y enviamos un sse si el item es de RasterFy
  */
  #[Route('rfyform/ntga/{key}', methods:['POST'])]
	public function setNtgaFromRasterF5(
    Request $req, SecurityBasic $sec, FcmRepository $fcmEm, WaSender $waS, String $key
  ): Response
	{

    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    $result = ['abort' => false, 'msg' => 'ok'];
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

    $fcmEm->setDataNTGA($data);
    
    return $this->json($result);
  }

  /**
   * Guardamos el item enviado desde Yonkeros App
  */
  #[Route('rfyform/item/{key}', methods:['POST'])]
	public function sendProduct(
    Request $req, WaSender $wh, SecurityBasic $sec, ItemsRepository $itemEm, MyFsys $fs, String $key
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

    $sinc = new SincronizerItem($fs);
    $sinc->build($data);

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

  /** 
   * Enviamos la notificacion de nueva solicitud o cotizacion a los contactos
  */
  #[Route('rfyform/make_push/{key}', methods:['POST'])]
	public function sentNotification(
    Request $req, SecurityBasic $sec, FcmRepository $fcmEm, 
    ItemsRepository $itemEm, WaSender $waS, Pushes $push, String $key
  ): Response
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

    // Si existe la clave 'idwap' es que se ha indexado correctamente la imagen
    // en los servidores de Whatsapp, por lo que se guarda el id de la imagen en D.B.
    $itemEm->updateImgWa($data);

    // $contacts = $fcmEm->getContactsForSend($data);
    file_put_contents($data['type'].'_data.json', json_encode($data));
    // file_put_contents($data['type'].'_contacts.json', json_encode($contacts));

    $track = new TrackProv($push, $waS, $data, []);
    $result = $track->builderTrack(
      $this->getParameter('fbSended'), $this->getParameter('fbFails')
    );
    // if(count($contacts) == 0) {
    //   $result = ['abort' => true, 'msg' => 'X Sin contactos'];
    // }else{
    //   $track = new TrackProv($push, $waS, $data, $contacts);
    //   $result = $track->builderTrack(
    //     $this->getParameter('fbSended'), $this->getParameter('fbFails')
    //   );
    // }

    return $this->json($result);
  }

  /**
   * Endpoint para subir Archivos
   */
  #[Route('rfyform/file/{key}', methods: ['GET', 'POST', 'DELETE'])]
  public function rfyFile(Request $req, SecurityBasic $sec, String $key): Response
  {
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    if($req->getMethod() == 'GET') {
      return $this->json(['abort' => true, 'body' => 'Método no permitido'], 401);
    }

    if($req->getMethod() == 'DELETE') {

      $datos = json_decode($req->getContent(), true);

    }elseif($req->getMethod() == 'POST') {

      $datos = $this->toArray($req, 'datos');
      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->json(['abort' => true, 'body' => 'Error en los datos'], 400);
      }
    }

    $carpeta = $datos['folder'] ?? null;
    $filename = $datos['filename'] ?? null;
    if (!$carpeta || !$filename) {
      return $this->json(['abort' => true, 'body' => 'Faltan datos'], 400);
    }

    if($carpeta == 'meta_data') {
      $subPath = '/rfy/'.$carpeta;
    }else{
      $subPath = '/rfy/inv_images/'.$carpeta;
    }
    $rutaCarpeta = $this->getParameter('kernel.project_dir') . '/public_html'. $subPath;

    if (!file_exists($rutaCarpeta)) {
      try {
          mkdir($rutaCarpeta, 0755, true);
      } catch (\Throwable $th) {
          return $this->json(['abort' => true, 'body' => 'Error al crear carpeta' . $subPath], 400);
      }
    }

    if($req->getMethod() == 'DELETE') {
      try {
          unlink($rutaCarpeta.'/'.$filename);
      } catch (\Throwable $th) {
          return $this->json([], 400);
      }
      return $this->json([], 201);
    }
    
    if($req->getMethod() == 'POST') {

      if($carpeta == 'meta_data') {
          file_put_contents($rutaCarpeta.'/'.$filename, json_encode($datos['meta']));
      }else{

          $foto = $req->files->get('foto');
          if (!$foto instanceof UploadedFile) {
              return $this->json(['abort' => true, 'body' => 'No se ha subido ningúna foto'], 401);
          }
          
          $foto->move($rutaCarpeta, $filename);
      }

      return $this->json([
          'abort' => false,
          'body' => 'Archivo subido correctamente',
          'foto' => $filename,
          'url' => $subPath.'/'.$filename
      ], 201);
    }

    return $this->json(['abort' => true, 'body' => 'Método no permitido'], 405);
  }

  /**
   * Endpoint para subir Archivos
   */
  #[Route('rfyform/file-iso/{key}', methods: ['POST'])]
  public function ynkFile(
    Request $req, SecurityBasic $sec, WaSender $wh, String $key
  ): Response
  {
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    if($req->getMethod() == 'POST') {

      $carpeta = $req->query->get('folder') ?? null;
      $filename = $req->query->get('filename') ?? null;
      $upWa = ($req->query->has('upWa')) ? $req->query->get('upWa') : '';

      if (!$carpeta || !$filename) {
        return $this->json(['abort' => true, 'body' => 'X Faltan datos'], 400);
      }
      $subPath = '/rfy/inv_images/'.$carpeta;
      $rutaCarpeta = $this->getParameter('kernel.project_dir') . '/public_html'. $subPath;
  
      if (!file_exists($rutaCarpeta)) {
        try {
            mkdir($rutaCarpeta, 0755, true);
        } catch (\Throwable $th) {
            return $this->json(['abort' => true, 'body' => 'X Error al crear carpeta' . $subPath], 400);
        }
      }

      $foto = $req->getContent();
      if ($foto == '') {
        return $this->json(['abort' => true, 'body' => 'X No se ha subido ningúna foto'], 401);
      }

      try {
        file_put_contents($rutaCarpeta.'/'.$filename, $foto);
      } catch (\Throwable $th) {
        return $this->json([
          'abort' => false, 'body' => $th->getMessage(), 'uuid' => $req->query->get('uuid')
        ], 501);
      }
      
      if($upWa != null) {
        $wh->indexImage($rutaCarpeta.'/'.$filename, $foto);
      }

      $foto = '';
      return $this->json([
        'abort' => false, 'body' => 'ok', 'uuid' => $req->query->get('uuid')
      ], 201);
    }

    return $this->json(['abort' => true, 'body' => 'X Error al subir imagen'], 405);
  }
  
}
