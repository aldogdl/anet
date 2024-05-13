<?php

namespace App\Controller\AnetTrack;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\ResetCot;

class GetController extends AbstractController
{

  /** */
  #[Route('anet-track/reset-cot/{tokenBasic}/{idItem}/{waIdCot}', methods:['get'])]
  public function resetCot(Fsys $fSys, String $tokenBasic, String $idItem, String $waIdCot): Response
  {
    $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    $miTok = $this->getParameter('getAnToken');
    if($miTok == $tokenBasic) {
      $acc = new ResetCot($fSys, $idItem, $waIdCot);
      $resul = $acc->exe();
      $response = ['abort' => true, 'body' => $resul];
    }

    return $this->json($response);
  }

}
