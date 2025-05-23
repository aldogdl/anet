<?php

namespace App\Controller\RfyForm;

use App\Repository\FcmRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\MyFsys;
use App\Service\Pushes;
use App\Service\SecurityBasic;
use App\Service\SincronizerItem;

class GetController extends AbstractController
{

  /**
  * Este controlador maneja la funcionalidad de notificaciones push.
  * Valida la clave de seguridad, recupera el token del dispositivo y
  * envía una notificación push de prueba.
  */
  #[Route('rfyform/push-test/{device}/{waid}/{key}', methods:['GET'])]
	public function testPush(
    Request $req, SecurityBasic $sec, FcmRepository $fcmEm, Pushes $fcmSend, String $device, String $waid, String $key
  ): Response
	{

    $result = ['abort' => false, 'msg' => ''];
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    if($req->getMethod() == 'GET') {

      $token = '';
      $query = $req->query->get('test');
      if(strlen($query) > 20) {
        $token = $query;
        $query = '';
      }else{
        $entity = $fcmEm->getTokenByWaIdAndDevice($waid, $device);
        if($entity != null) {
          $token = $entity?->getTkfcm();
        }
      }

      if($token == '') {
        $result = ['abort' => true, 'msg' => 'X No se encontró el token del dispositivo'];
        return $this->json($result);
      }
      $result = $fcmSend->test($token);
      $result['abort'] = false;
    }

    return $this->json($result);
	}

  /**
  * Este controlador recupera los datos del item que se pretende
  * cotizar via formulario al haber presionado el boton de cotizar
  * via Formulario en un mensaje de whatsapp.
  */
  #[Route('rfyform/has-cot/{waId}', methods:['GET'])]
	public function hasCot(Request $req, MyFsys $fsys, String $waId): Response
	{
    $result = ['abort' => false, 'body' => []];
    if($req->getMethod() == 'GET') {
      $result = $fsys->getCotViaForm('waCotForm', $waId);
    }
    return $this->json($result);
	}

  /** 
  * Sincronizacion de dispositivos recuperamos las claves
  */
  #[Route('rfyform/sinc-dev/{waId}/{key}', methods:['GET'])]
	public function getSincDev(SecurityBasic $sec, MyFsys $fsys, String $waId, String $key): Response
	{
    $result = ['abort' => false, 'msg' => ''];
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    $sinc = new SincronizerItem($fsys);
    $sinc->get($waId);

    return $this->json($result);
  }

  /** 
  * BORRAR
  */
  #[Route('rfyform/test-connection', methods:['GET'])]
	public function testConnection(): Response
	{
    $result = ['ok' => 'Si funk!!'];
    return $this->json($result);
  }

  /** 
  * Este controlador maneja laS preubas internas
  */
  #[Route('rfyform/pruebas', methods:['GET'])]
	public function pruebasInternas(FcmRepository $fcmEm, Pushes $fcmSend, MyFsys $fsys): Response
	{
    $result = ['ok' => 'Si funk!!'];
    // $result = $fcmEm->setLogged('5213316195698');
    // $fcmEm->closeSessionWaAlls();
    // $result = $fsys->updateFechaLoginTo('rasterfy', '5213316195698');
    return $this->json($result);
  }

}
