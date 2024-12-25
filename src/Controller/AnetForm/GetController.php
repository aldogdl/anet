<?php

namespace App\Controller\AnetForm;

use App\Repository\FcmRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;
use App\Service\Pushes;
use App\Service\SecurityBasic;

class GetController extends AbstractController
{

  /** 
  * Este controlador maneja la funcionalidad de notificaciones push.
  * Valida la clave de seguridad, recupera el token del dispositivo y envía una notificación push.
  */
  #[Route('form/push/{device}/{waid}/{key}', methods:['GET', 'POST', 'UPDATE'])]
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
      
      $token = $fcmEm->getTokenByWaId($waid);
      if(!$token) {
        $result = ['abort' => true, 'msg' => 'X No se encontró el token del dispositivo'];
        return $this->json($result);
      }
      $result = $fcmSend->send([$token]);
    }

    return $this->json($result);
	}

}
