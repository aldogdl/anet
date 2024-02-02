<?php

namespace App\Service\WapiProcess;

use App\Entity\EstanqueReturn;
use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class SentTemplate
{

    private WebHook $wh;
    private WrapHttp $wapiHttp;
    private WaMsgMdl $msg;
    private array $paths;
    private array $template;
    private array $cotProgress;

    /** 
     * Unificacion para el envio de mensaje dentro de un proceso de COTIZACION
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress,
        array $template
    ){
        $this->wh          = $wh;
        $this->wapiHttp    = $wapiHttp;
        $this->msg         = $message;
        $this->cotProgress = $cotProgress;
        $this->paths       = $paths;
        $this->template    = $template;

        $cotProgress       = [];
    }

    /** */
    public function to(): void
    {
        $sended = [];
        $typeMsgToSent = 'text';
        $conm = new ConmutadorWa($this->msg->from, $this->paths['tkwaconm']);

        $typeMsgToSent = $this->template['type'];
        $conm->setBody($typeMsgToSent, $this->template);

        $result = $this->wapiHttp->send($conm);
        if($result['statuscode'] != 200) {
            $this->wh->sendMy('wa-wh', 'notSave', $result);
            return;
        }

        $objMdl = $conm->setIdToMsgSended($this->msg, $result);
        $this->cotProgress['wamid'] = $objMdl->id;

        $conm->bodyRaw = $this->template[$typeMsgToSent]['body'];
        $sended = $objMdl->toArray();

        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $fSys = new FsysProcess($this->paths['tracking']);
        $estanque = $fSys->getEstanqueOf($this->msg->from);
        $returnData = new EstanqueReturn($estanque, $this->cotProgress, $this->paths['hasCotPro'], 'less');

        $this->wh->sendMy(
            'wa-wh', 'notSave', [
                'subEvent' => $this->msg->subEvento,
                'recibido' => $returnData['baitProgress'],
                'estanque' => $returnData['estData'],
                'enviado'  => $sended,
            ]
        );
    }

}