<?php

namespace App\Controller\Catalogo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;

class GetController extends AbstractController
{

  /** 
   * Guardamos el item enviado desde AnetFrom
  */
  #[Route('anet-cat/item/', methods:['GET'])]
	public function items(Request $req, ItemsRepository $itemEm): Response
	{

    $result = ['abort' => true, 'msg' => '', 'results' => []];
    $querys = $req->query->all();
    if(count($querys) == 0) {
      $result['body'] = 'TODO recuperar todos los items paginados';
      return $this->json($result);
    }

    if(array_key_exists('id', $querys)) {
      $dql = $itemEm->getItemById($querys['id']);
      $result['abort'] = false;
      $result['results'] = $dql->getArrayResult();
      return $this->json($result);
    }

    $result['msg'] = 'No hay acción relacionada con la solicitud';
	  return $this->json($result);
	}

}
