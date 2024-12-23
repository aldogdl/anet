<?php

namespace App\Controller\AnetForm;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;
use App\Service\ItemTrack\WaSender;
use App\Service\HeaderItem;
use App\Service\SecurityBasic;

class GetController extends AbstractController
{

  /** 
   * Metodo auxiliar para repetir la axión de hacer checkin, es decir, en el momento
   * que se recibe una solicitud, cotizacion o publicacion se envia automaticamente
   * una notificacion a AnetTrack, pero por si alguna razón no se recibe o se quiere
   * repetir el proceso para un item en particular, desde AnetTrack se llama a esta API.
  */
  #[Route('form/push/{device}/{waid}/key', methods:['GET', 'POST', 'UPDATE'])]
	public function testPush(
    Request $req, SecurityBasic $sec, ItemsRepository $itemEm, String $device, String $waid, String $key
  ): Response
	{
    $result = ['abort' => false, 'msg' => ''];
    if(!$sec->isValid($key)) {
      $result = ['abort' => true, 'msg' => 'X Permiso denegado'];
      return $this->json($result);
    }

    return $this->json($result);
	}

}
