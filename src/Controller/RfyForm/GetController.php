<?php

namespace App\Controller\RfyForm;

use App\Repository\FcmRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\Pushes;
use App\Service\SecurityBasic;

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

      $token = $fcmEm->getTokenByWaIdAndDevice($waid, $device);
      if($token == null) {
        $result = ['abort' => true, 'msg' => 'X No se encontró el token del dispositivo'];
        return $this->json($result);
      }
      $result = $fcmSend->test($token?->getTkfcm());
      $result['abort'] = false;
    }

    return $this->json($result);
	}

}
