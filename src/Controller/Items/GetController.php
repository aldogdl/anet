<?php

namespace App\Controller\Items;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\ItemsRepository;
use App\Repository\PaginatorQuery;
use App\Service\ItemTrack\WaSender;
use App\Service\HeaderItem;
use App\Service\MyFsys;

class GetController extends AbstractController
{

  /** 
   * Metodo auxiliar para repetir la axión de hacer checkin, es decir, en el momento
   * que se recibe una solicitud, cotizacion o publicacion se envia automaticamente
   * una notificacion a Rasview, pero por si alguna razón no se recibe o se quiere
   * repetir el proceso para un item en particular, desde Rasview se llama a esta API.
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
    $result['sendMy']  = $sendMy;
    if(!$sendMy) {
      $result['header'] = $head['header'];
    }
    return $this->json($result);
	}

  /** 
   * Recuperación y gestion para los item de tipo solicitud
  */
  #[Route('items/type/{type}', methods:['GET', 'DELETE'])]
	public function itemsTypeSolicitud(Request $req, ItemsRepository $itemEm, MyFsys $fsys, String $type): Response
	{
    $paginator = new PaginatorQuery();
    $result = ['paging' => ['total' => 0], 'result' => []];

    $limit = 20;
    $arrayType = 'min';
    $params = $req->query->all();
    $recovery = [];
    $waIdPedido = '';
    $fecha_hoy = strtotime('today 5:00:00');
    $milisegundos = $fecha_hoy * 1000;
    $isForSinc = false;
    
    // Quien es el que solicitó la lista, si viene este parametro
    // Significa que se recuperan los ultimos 20 items tipo solicitud
    // excepto los que coincidan con waId que requiere la lista.
    if(array_key_exists('fromWaId', $params)) {

      $waIdPedido = $params['fromWaId'];
      if(mb_strpos($waIdPedido, '_sinc') !== false) {
        $isForSinc = true;
        $waIdPedido = explode('_sinc', $waIdPedido)[0];
      }

      $estosNo = [];
      if(!$isForSinc) {
        // Para poder recuperar todos los items que no ha descargado el solicitante
        // la tecnica es crear un archivo json para saber cuales items ya descargo
        $recovery = $fsys->getContent('prodSols', $waIdPedido.'.json');
        if($recovery == null || count($recovery) == 0) {
          $recovery = [];
        }
        // Los items estan organizados en el archivo con pares clave-valor
        // donde la clave es la fecha de hoy apartir de las 5 am.
        if(array_key_exists($milisegundos, $recovery)) {
          $estosNo = $recovery[$milisegundos];
        }
      }

      $query = $itemEm->getItemsCompleteByType($type, $waIdPedido, $estosNo, $isForSinc);
      $limit = 10;
      $arrayType = 'max';

    }else{
      $query = $itemEm->getItemsAsRefByType($type);
    }

    $offset = 1;
    if(array_key_exists('offset', $params)) {
      $offset = (integer) $params['offset'];
    }

    $result = $paginator->pagine($query, $limit, $arrayType, $offset);
    $downs = [];
    if($result['paging']['results'] > 0 && $waIdPedido != '') {
      for ($i=0; $i < $result['paging']['results']; $i++) {
        $downs[] = $result['result'][$i]['id'];
      }
      if(array_key_exists($milisegundos, $recovery)) {
        $currents = $recovery[$milisegundos];
        $recovery[$milisegundos] = array_merge($downs, $currents);
      }else{
        $recovery[$milisegundos] = $downs;
      }
      $fsys->setContent('prodSols', $params['fromWaId'].'.json', $recovery);
    }
    return $this->json($result);
	}

  /** */
  #[Route('item', methods:['GET', 'DELETE'])]
	public function item(Request $req, ItemsRepository $itemEm): Response
	{
    $params = $req->query->all();
    if(count($params) != 0) {
      if(array_key_exists('field', $params) && array_key_exists('value', $params)) {
        
        $query = $itemEm->getItemByCampoValor($params);
        $item = $query->getArrayResult();
        if(count($item) > 1) {
          return $this->json($item);
        }
        return $this->json(($item) ? $item[0] : []);
      }
    }

    return $this->json([]);
	}

}
