<?php

namespace App\Service\WapiRequest;

use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\CostoProcess;
use App\Service\WapiResponse\DetallesProcess;
use App\Service\WapiResponse\FotosProcess;
use App\Service\WapiResponse\WrapHttp;
use App\Service\WebHook;

class ReqDetallesProcess {

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
  public function exe(bool $btnPressAsNew = false, bool $btnPressNormal = false) {

    $this->fileCot = $this->cotTransit->getCotizacionInTransit();

    $obj = new DetallesProcess($this->cotTransit->pathFull);

    $isWithBtn = '';
    if($btnPressAsNew) {
        $isWithBtn = 'asNew';
    }
    if($btnPressNormal) {
        $isWithBtn = 'normal';
    }

    $isValid = $obj->isValid($this->message, $this->fileCot, $isWithBtn);
    if($isValid != '') {

        if($isValid == 'image') {
            $this->message = $obj->buildResponse($this->message, []);
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
            return;
        }

        if($isValid == 'notFotosReply') {
            $obj = new FotosProcess($this->cotTransit->pathFull);
            $this->conm->setBody('interactive', $obj->getMessageError($isValid, $this->fileCot['wamid']));
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
        }else{
            $this->conm->setBody('text', $obj->getMessageError($isValid, $this->fileCot['wamid']));
        }

        $this->wapiHttp->send($this->conm, true);
        return;
    }

    // Cambiamos a costo
    $this->changeToCosto();
    return;
  }

  /** */
  private function changeToCosto() {

    $this->fileCot = $this->cotTransit->updateStepCotizacionInTransit(2, $this->fileCot);
    $obj = new CostoProcess($this->cotTransit->pathFull);

    $this->conm->setBody('text', $obj->getMessage($this->fileCot));
    // Enviamos el mensaje a whatsapp
    $this->wapiHttp->send($this->conm, true);

    $this->message = $obj->buildResponse($this->message, $this->conm->toArray());
    // Enviamos el mensaje a backCore
    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
    return;
  }
}