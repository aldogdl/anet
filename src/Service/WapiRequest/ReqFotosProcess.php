<?php

namespace App\Service\WapiRequest;

use App\Service\WebHook;
use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\DetallesProcess;
use App\Service\WapiResponse\FotosProcess;
use App\Service\WapiResponse\WrapHttp;

class ReqFotosProcess {

  private IsCotizacionMessage $cotTransit;
  private ConmutadorWa $conm;
  private WrapHttp $wapiHttp;
  private WebHook $whook;
  private array $message;

  private array $fileCot;

  /** */
  public function __construct(
    array $msg, ConmutadorWa $conmutador,
    IsCotizacionMessage $transit, WrapHttp $whttp, WebHook $wh
  ) {
    $this->cotTransit = $transit;
    $this->message = $msg;
    $this->conm = $conmutador;
    $this->whook = $wh;
    $this->wapiHttp = $whttp;
  }

  /** Aqui se inicializa una cotizacion en transitos */
  public function initializaCot() {

    $this->cotTransit->setStepCotizacionInTransit(0);
    $obj = new FotosProcess($this->cotTransit->pathFull);
    $this->conm->setBody('text', $obj->getMessage());
    $this->wapiHttp->send($this->conm);

    $this->message = $obj->buildResponse($this->message, $this->conm->toArray());
    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
  }

  /** */
  public function exe(bool $isNotFoto) {

    $this->fileCot = $this->cotTransit->getCotizacionInTransit();

    $obj = new FotosProcess($this->cotTransit->pathFull);
    
    $isValid = $obj->isValid($this->message, $this->fileCot);
    $this->fileCot = $obj->fileCot;
    
    if($isValid != '') {

        if($isNotFoto) {
          $this->fileCot['values']['fotos'][] = 'SIN FOTOS';
          file_put_contents($this->cotTransit->pathFull, json_encode($this->fileCot));
          file_put_contents($this->cotTransit->pathFull.'.det', '');
        }else{
          // Resulto invalido y no ha presionado el btn de SIN FOTOS
          $tipo = ($isValid == 'notFotosReply') ? 'interactive' : 'text';
          $this->conm->setBody($tipo, $obj->getMessageError($isValid, $this->fileCot["wamid"]));
          $result = $this->wapiHttp->send($this->conm, true);
          return;
        }
    }

    $this->changeToDetalles();
  }

  /** */
  private function changeToDetalles() {

    $this->fileCot = $this->cotTransit->updateStepCotizacionInTransit(1, $this->fileCot);
    
    $det = new DetallesProcess($this->cotTransit->pathFull);

    if($det->isMsgInique()) {
      $this->conm->setBody('interactive', $det->getMessage($this->fileCot["wamid"]));
      // Enviamos el mensaje a whatsapp
      $this->wapiHttp->send($this->conm, true);
    }

    $this->message = $det->buildResponse($this->message, $this->conm->toArray());
    // Enviamos el mensaje a backCore
    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
  }
}