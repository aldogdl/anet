<?php

namespace App\Controller\AnetTrack;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\ItemTrack\Fsys;
use App\Service\ItemTrack\ResetCot;

class GetController extends AbstractController
{

  /** */
  #[Route('anet-track/resent-msgtrack/{idDbSr}/{waIdCot}/{tokenBasic}', methods:['get'])]
  public function resentMsgTrackDeep(Fsys $fSys, String $tokenBasic, String $idDbSr, String $waIdCot): Response
  {
    $response = ['abort' => true, 'body' => ''];
    if($this->isValid($tokenBasic)) {
      $acc = new ResetCot($fSys, $idDbSr, $waIdCot);
      $resul = $acc->exe();
      $response = ['abort' => false, 'body' => $resul];
    }else{
      $response = ['abort' => true, 'body' => '¿Que haces aquí?'];
    }

    return $this->json($response);
  }

  /** 
   * [V6]
   * Eliminamos el archivo de login de un cotizador o todos si asi lo indica
   * el paramentro waIdCot
  */
  #[Route('anet-track/del-init-login/{waIdCot}/{tokenBasic}', methods:['get'])]
  public function delInitLoginCot(Fsys $fSys, String $tokenBasic, String $waIdCot): Response
  {
    $response = ['abort' => true, 'body' => 0];
    if($this->isValid($tokenBasic)) {
      $borradosCant = $fSys->deleteInitLoginFile($waIdCot);
      $response = ['abort' => false, 'body' => $borradosCant];
    }else{
      $response = ['abort' => true, 'body' => 0];
    }

    return $this->json($response);
  }

  /** 
   * Eliminamos el archivo que se encarga de detener los stt de wa mientras que
   * se esta realizando una cotizacion
  */
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
  public function isValid(String $token): bool {

    $tok = base64_decode($token);
    $miTok = $this->getParameter('getAnToken');
    return ($miTok == $tok) ? true : false;

  }

}
