<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;

class GetController extends AbstractController
{

  /** 
   * Recuperamos los datos del cotizador desde el archivo json
  */
  #[Route('security-basic/get-data-ctc/{token}/{slug}/', methods:['get'])]
  public function getDataContact(
    SecurityBasic $lock, String $token, String $slug
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('dtaCtc') . $slug . '.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
        // $data = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
      }
    }

    return new Response($data);
    // return $this->json($data);
  }

  /** 
   * Recuperamos el inventario del cotizador desde el archivo json
  */
  #[Route('security-basic/get-inv-ctc/{token}/{waId}/', methods:['get'])]
  public function getInvContact(
    SecurityBasic $lock, String $token, String $waId
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('invCtc') . $waId . '.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
        $data = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
      }
    }

    return $this->json($data);
  }

}
