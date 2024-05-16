<?php

namespace App\Controller\AnetTrack;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
    $tok = base64_decode($tokenBasic);
    $response = ['abort' => true, 'body' => ''];
    $miTok = $this->getParameter('getAnToken');
    if($miTok == $tok) {
      $acc = new ResetCot($fSys, $idItem, $waIdCot);
      $resul = $acc->exe();
      $response = ['abort' => false, 'body' => $resul];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

  /** */
  #[Route('anet-track/liberar-stt/{tokenBasic}/{waIdCot}', methods:['get'])]
  public function liberarStt(Fsys $fSys, String $tokenBasic, String $waIdCot): Response
  {
    $tok = base64_decode($tokenBasic);
    $response = ['abort' => true, 'body' => ''];
    $miTok = $this->getParameter('getAnToken');
    if($miTok == $tok) {
      $fSys->delete('/', $waIdCot."_stopstt.json");
      $response = ['abort' => false, 'body' => 'ok'];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

  /** */
  #[Route('anet-track/cooler/{tokenBasic}/{waIdCot}', methods:['get', 'post'])]
  public function cooler(Request $req, Fsys $fSys, String $tokenBasic, String $waIdCot): Response
  {
    $tok = base64_decode($tokenBasic);
    $response = ['abort' => true, 'body' => ''];
    
    $miTok = $this->getParameter('getAnToken');
    if($miTok == $tok) {
      $cooler = [];
      if($req->getMethod() == 'GET') {

        $cooler = $fSys->getContent('waEstanque', $waIdCot.".json");

      }elseif($req->getMethod() == 'POST') {
        $data = json_decode($req->getContent(), true);
        $fSys->setContent('waEstanque', $waIdCot.".json", $data);
      }
      $response = ['abort' => false, 'body' => $cooler];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

}
