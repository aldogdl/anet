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
    public FsysProcess $fSys;
    private array $paths;
    private array $template;
    private array $cotProgress;
    public String $subEvento;
    public bool $hasTemplate = false;
    public bool $isInitFsys = false;

    /** 
     * Unificacion para el envio de mensaje dentro de un proceso de COTIZACION
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress,
        array $template = []
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
    public function updateCotProgress(array $cp) {
        $this->cotProgress = $cp;
    }

    /** */
    public function saveCotProgress(): void
    {
        if(!$this->isInitFsys){
            $this->fSys = new FsysProcess($this->paths['cotProgres']);
            $this->isInitFsys = true;
        }else{
            $this->fSys->setPathBase($this->paths['cotProgres']);
        }
        $this->fSys->setContent($this->msg->from.'.json', $this->cotProgress);
    }
    
    /** */
    public function getTemplate(array $templateOtra = []): void
    {
        $this->hasTemplate = false;
        if(!$this->isInitFsys){
            $this->fSys = new FsysProcess($this->paths['waTemplates']);
            $this->isInitFsys = true;
        }else{
            $this->fSys->setPathBase($this->paths['waTemplates']);
        }
        if(count($templateOtra) == 0) {
            $this->template = $this->fSys->getContent($this->cotProgress['current'].'.json');
        }else{
            $this->template = $templateOtra;
        }

        if(count($this->template) > 0) {
            // Buscamos si contiene AnetLanguage para decodificar
            $deco = new DecodeTemplate($this->cotProgress);
            $this->template = $deco->decode($this->template);
            
            $contexto = '';
            if(array_key_exists('wamid_cot', $this->cotProgress)) {
                $contexto = $this->cotProgress['wamid_cot'];
            }else{
                if(strlen($this->msg->context) > 0) {
                    $contexto = $this->msg->context;
                }
            }

            if(strlen($contexto) > 0) {
                $this->template['context'] = $contexto;
                $this->cotProgress['wamid_cot'] = $contexto;
            }
            $this->hasTemplate = true;
        }
    }

    /** */
    public function sent(): void
    {
        $sended = [];
        if($this->hasTemplate) {
            
            $typeMsgToSent = $this->template['type'];
            
            $conm = new ConmutadorWa($this->msg->from, $this->paths['tkwaconm']);
            $conm->setBody($typeMsgToSent, $this->template);
    
            $result = $this->wapiHttp->send($conm);
            if($result['statuscode'] != 200) {
                $this->wh->sendMy('wa-wh', 'notSave', $result);
                return;
            }
            
            // Extraemos el IdItem del mensaje que se va a enviar al cotizador cuando se
            // responde con otro mensaje interactivo
            $idItem = '0';
            if(array_key_exists('action', $this->template)) {
                if(array_key_exists('buttons', $this->template['action'])) {
                    $idItem = $this->template['action']['buttons'][0]['reply']['id'];
                    $partes = explode('_', $idItem);
                    $idItem = $partes[1];
                }
                $conm->bodyRaw = ['body' => $this->template['body'], 'idItem' => $idItem];
            }else{
                $conm->bodyRaw = $this->template[$this->template['type']];
            }

            $objMdl = $conm->setIdToMsgSended($this->msg, $result);
            $this->cotProgress['wamid'] = $objMdl->id;
            $this->sentToEventCore($objMdl->toArray());
        }
    }
    
    /** */
    public function sentToEventCore(array $sended = []) {
        
        // Recuperamos el Estanque del cotizador que esta iniciando sesion
        $this->fSys->setPathBase($this->paths['tracking']);
        $estanque = $this->fSys->getEstanqueOf($this->msg->from);
        $est = new EstanqueReturn($estanque, $this->cotProgress, $this->paths['hasCotPro'], 'less');
        $returnData = $est->toArray();

        $this->wh->sendMy(
            'wa-wh', 'notSave', [
                'subEvent' => $this->subEvento,
                'recibido' => $returnData['baitProgress'],
                'estanque' => $returnData['estData'],
                // 'enviado'  => $sended,
            ]
        );
    }
}