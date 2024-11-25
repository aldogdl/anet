<?php

namespace App\Controller\Items;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;
use App\Repository\PaginatorQuery;

class GetController extends AbstractController
{

  /** 
   * 
  */
  #[Route('items/sol/', methods:['GET', 'DELETE'])]
	public function itemsTypeSolicitud(Request $req, ItemsRepository $itemEm): Response
	{
    $type = 'solicita';
    $paginator = new PaginatorQuery();
    $result = ['paging' => ['total' => 0], 'result' => []];

    $params = $req->query->all();
    if(count($params) == 0) {
      $query = $itemEm->getItemsAsRefByType($type);
    }else{
      $query = $itemEm->getItemsAsRefByType($type);
    }

    $offset = 1;
    if(array_key_exists('offset', $params)) {
      $offset = (integer) $params['offset'];
    }

    $result = $paginator->pagine($query, 20, 'min', $offset);
    return $this->json($result);
	}

  /** 
   * 
  */
  #[Route('items/cot/', methods:['GET', 'DELETE'])]
	public function itemsTypeCotizacion(Request $req, ItemsRepository $itemEm): Response
	{

    $result = ['abort' => true, 'msg' => ''];
    
    $query = $req->query->all();
    if(count($query) == 0) {
      $result['msg']  = 'X No se recibieron instrucciones';
    }else{
      // $id = $itemEm->setProduct($data);
      // if($id == 0) {
      //   $result['msg']  = 'X No se logró guardar el producto en D.B.';
      //   return $this->json($result);
      // }
    }

    return $this->json($result);

	}

}
