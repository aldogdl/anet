<?php

namespace App\Service\WapiRequest;

use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\DetallesProcess;
use App\Service\WapiResponse\FotosProcess;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WebHook;

class ReqFotosProcess {

  private IsCotizacionMessage $cotTransit;
  private ConmutadorWa $conm;
  private WrapHttp $wapiHttp;
  private WebHook $whook;
  private array $message;

  private array $fileCot;

  /** */
  public function __construct(array $msg, ConmutadorWa $conmutador,
  IsCotizacionMessage $transit, WrapHttp $whttp, WebHook $wh)
  {
    $this->cotTransit = $transit;
    $this->message = $msg;
    $this->conm = $conmutador;
    $this->whook = $wh;
    $this->wapiHttp = $whttp;
  }

  /** */
  public function exe(bool $isNotFoto) {

    $this->fileCot = $this->cotTransit->getCotizacionInTransit();

    $obj = new FotosProcess($this->cotTransit->pathFull);
    
    $isValid = $obj->isValid($this->message, $this->fileCot);
    if($isValid != '') {
        if(!$isNotFoto) {
            $this->conm->setBody('interactive', $obj->getMessageError($isValid, $this->fileCot));
            $result = $this->wapiHttp->send($this->conm, true);
            return;
        }
    }

    $this->changeToDetalles();
  }

  /** */
  private function changeToDetalles() {

     // Cambiamos a detalles
     $this->fileCot = $this->cotTransit->updateStepCotizacionInTransit(1, $this->fileCot);
     $obj = new DetallesProcess($this->cotTransit->pathFull);
     $this->conm->setBody('interactive', $obj->getMessage($this->fileCot));
     $result = $this->wapiHttp->send($this->conm, true);
 
     $this->message = $obj->buildResponse($this->message, $this->conm->toArray());
     $this->whook->sendMy('wa-wh', 'notSave', $this->message);
  }
}