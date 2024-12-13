<?php

namespace App\Controller\Items;

use App\Dtos\HeaderDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;
use App\Repository\PaginatorQuery;
use App\Service\ItemTrack\WaSender;
use App\Service\HeaderItem;

class GetController extends AbstractController
{

  /** 
   * Metodo auxiliar para repetir la axión de hacer checkin, es decir, en el momento
   * que se recibe una solicitud, cotizacion o publicacion se envia automaticamente
   * una notificacion a AnetTrack, pero por si alguna razón no se recibe o se quiere
   * repetir el proceso para un item en particular, desde AnetTrack se llama a esta API.
  */
  #[Route('item/checkin/{id}', methods:['GET', 'DELETE'])]
	public function itemRecovery(Request $req, ItemsRepository $itemEm, WaSender $wh, int $id): Response
	{

    $params = $req->query->all();
    $sendMy = false;
    if(array_key_exists('sendMy', $params)) {
      $sendMy = ($params['sendMy'] == '0') ? false : true;
    }

    $data = $itemEm->find($id);

    if($data) {
      $builder = new HeaderItem();
      $head = $builder->build($data->toJsonForHead());
      if($sendMy) {
        $wh->sendMy($head);
      }
    }

    $result['abort']   = false;
    $result['anet_id'] = $id;
    $result['idItem']  = $data->getIdItem();
    $result['sendMy'] = $sendMy;
    if(!$sendMy) {
      $result['header'] = $head['header'];
    }
    return $this->json($result);
	}

  /** 
   * Recuperación y gestion para los item de tipo solicitud
  */
  #[Route('items/sol/', methods:['GET', 'DELETE'])]
	public function itemsTypeSolicitud(Request $req, ItemsRepository $itemEm): Response
	{
    $type = 'solicita';
    $paginator = new PaginatorQuery();
    $result = ['paging' => ['total' => 0], 'result' => []];

    $query = $itemEm->getItemsAsRefByType($type);

    $offset = 1;
    $params = $req->query->all();
    if(array_key_exists('offset', $params)) {
      $offset = (integer) $params['offset'];
    }

    $result = $paginator->pagine($query, 20, 'min', $offset);
    return $this->json($result);
	}

  /** 
   * Recuperación y gestion para los item de tipo cotizacion
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

  /** */
  #[Route('item', methods:['GET', 'DELETE'])]
	public function item(Request $req, ItemsRepository $itemEm): Response
	{
    $params = $req->query->all();
    if(count($params) != 0) {
      if(array_key_exists('field', $params)) {
        if($params['field'] == 'id') {
          $query = $itemEm->getItemById($params['value']);
          $item = $query->getArrayResult();
          return $this->json(($item) ? $item[0] : []);
        }
      }
    }

    return $this->json([]);
	}

}
