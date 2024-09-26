<?php

namespace App\Controller\AnetCraw;

use App\Repository\ItemsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;

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
    // Comienza el buffer de salida con Gzip
    ob_start('ob_gzhandler');

    // Crea la respuesta JSON
    $response = $this->json(['isOk' => true, 'msg' => 'ok', 'body' => $items]);

    // Finaliza el buffer y devuelve la respuesta comprimida
    $content = ob_get_clean(); // Obtiene el contenido del buffer
    $response->setContent($content); // Establece el contenido comprimido
    $response->headers->set('Content-Encoding', 'gzip'); // Establece el encabezado de compresión

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
