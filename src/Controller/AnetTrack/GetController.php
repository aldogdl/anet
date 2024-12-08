<?php

namespace App\Controller\AnetTrack;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\ItemTrack\Fsys;
use App\Service\ItemTrack\ResetCot;

class GetController extends AbstractController
{

  /** */
  #[Route('anet-track/reset-cot/{idItem}/{waIdCot}/{tokenBasic}', methods:['get'])]
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

  /** 
   * [V6]
  */
  #[Route('anet-track/del-init-login/{waIdCot}/{tokenBasic}', methods:['get'])]
  public function delInitLoginCot(Fsys $fSys, String $tokenBasic, String $waIdCot): Response
  {
    $response = ['abort' => true, 'body' => ''];
    if($this->isValid($tokenBasic)) {
      $borradosCant = $fSys->deleteInitLoginFile($waIdCot);
      $response = ['abort' => false, 'body' => 'Ok Borrados '.$borradosCant];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

  /** */
  #[Route('anet-track/liberar-stt/{waIdCot}/{tokenBasic}', methods:['get'])]
  public function liberarStt(Fsys $fSys, String $tokenBasic, String $waIdCot): Response
  {
    $response = ['abort' => true, 'body' => ''];
    if($this->isValid($tokenBasic)) {
      $fSys->delete('/', $waIdCot."_stopstt.json");
      $response = ['abort' => false, 'body' => 'ok'];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

  /** */
  #[Route('anet-track/cooler/{waIdCot}/{delStopStt}/{tokenBasic}', methods:['get', 'post'], defaults:['delStopStt' => 0])]
  public function cooler(Request $req, Fsys $fSys, String $tokenBasic, String $waIdCot, int $delStopStt = 0): Response
  {
    $response = ['abort' => true, 'body' => ''];
    
    if($this->isValid($tokenBasic)) {
      $cooler = [];
      // Eliminamos la marca de detencion de Status
      if($delStopStt == 1) {
        $fSys->delete('/', $waIdCot."_stopstt.json");
      }
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

  /** */
  public function isValid(String $token): bool {

    $tok = base64_decode($token);
    $miTok = $this->getParameter('getAnToken');
    return ($miTok == $tok) ? true : false;

  }

}
