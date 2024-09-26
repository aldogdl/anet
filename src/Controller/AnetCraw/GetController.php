<?php

namespace App\Controller\AnetCraw;

use App\Repository\ItemsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Todos los get sin Token para la app de AnetShop
 */
class GetController extends AbstractController
{

  /** 
   * Buscamos productos de otros cotizadores 
  */
  #[Route('items', methods:['GET'])]
  public function items(Request $req, ItemsRepository $itemEm): Response
  {
    $lastTime = $req->query->get('last');
    $dql = $itemEm->getLastItems( $lastTime );
    $items = $dql->getArrayResult();

    if (empty($items)) {
      return new JsonResponse(['isOk' => false, 'msg' => 'No hay items por el momento'], 200);
    }

    $responseData = ['isOk' => true, 'msg' => 'ok', 'body' => $items];
    $jsonContent = json_encode($responseData, JSON_THROW_ON_ERROR);

    $response = new Response($jsonContent);
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Content-Encoding', 'gzip');

    // Comprime la respuesta
    $gzipContent = gzencode($jsonContent, 9);
    $response->setContent($gzipContent);

    return $response;
  }

  /** */
  #[Route('security-basic/{token}/data-cnx/', methods:['get'])]
  public function recoveryDataCnx(SecurityBasic $lock, String $token): Response
  {
    $dta = [];
    if($lock->isValid($token)) {
      $dta = $lock->getDtCnx();
    }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $dta]);
  }

}
