<?php

namespace App\Service\WapiRequest;

use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\CostoProcess;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WebHook;

class ReqCostoProcess {

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
  public function exe() {

    $this->fileCot = $this->cotTransit->getCotizacionInTransit();

    $obj = new CostoProcess($this->cotTransit->pathFull);
    $isValid = $obj->isValid($this->message, $this->fileCot);
    if($isValid != '') {
        $this->conm->setBody('text', $obj->getMessageError($isValid, $this->fileCot));
        $result = $this->wapiHttp->send($this->conm, true);
        return;
    }

    $this->cotTransit->finishCotizacionInTransit();

    $this->conm->setBody('text', $obj->getMessageGrax($this->fileCot));
    $result = $this->wapiHttp->send($this->conm, true);

    $msg = $obj->buildResponse($this->message, $this->conm->toArray(), true);
    $this->whook->sendMy('wa-wh', 'notSave', $msg);
  }

}