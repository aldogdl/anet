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
    // // Crear la respuesta JSON
    // $response = $this->json(['isOk' => true, 'msg' => 'ok', 'body' => $items]);

    // // Establecer el encabezado de compresión
    // $response->headers->set('Content-Encoding', 'gzip');

    // Verifica que $items no esté vacío o nulo
    if (is_null($items)) {
        return new JsonResponse(['isOk' => false, 'msg' => 'No items found'], 404);
    }

    // Crea la respuesta JSON
    $responseData = ['isOk' => true, 'msg' => 'ok', 'body' => $items];
    $jsonContent = json_encode($responseData);

    // Maneja errores de json_encode
    if ($jsonContent === false) {
        return new JsonResponse(['isOk' => false, 'msg' => 'Error encoding JSON'], 500);
    }

    // Comienza el buffer de salida con Gzip
    ob_start('ob_gzhandler');
    echo $jsonContent; // Imprime el contenido JSON
    $content = ob_get_clean(); // Obtiene el contenido comprimido

    // Crea la respuesta
    $response = new Response($content);
    $response->headers->set('Content-Encoding', 'gzip');
    $response->headers->set('Content-Type', 'application/json'); // Establece el tipo de contenido

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
