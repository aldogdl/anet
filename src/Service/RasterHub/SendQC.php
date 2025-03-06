<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;
use App\Repository\FcmRepository;
use App\Service\ItemTrack\WaSender;

class SendQC
{
    private WaSender $waSender;
    private FcmRepository $fcmEm;
    private WaMsgDto $msg;

    /** */
    public function __construct(FcmRepository $fbm, WaSender $waS)
    {
        $this->fcmEm = $fbm;
        $this->waSender = $waS;
    }

    /** */
    public function exe(WaMsgDto $msg) : void 
    {
        $this->msg = $msg;
        $template = $this->build();
        if(count($template) == 0) {
            $this->waSender->sendText(
                '📵 Ocurrió un error al procesar tu solicitud, por favor '.
                'inténtalo nuevamente por favor.'
            );
            return;
        }
        
        $dql = $this->fcmEm->getAllByWaIdExcept($this->msg->from);
        $contacts = $dql->getResult();
        $rota = count($contacts);
        $prueb = [];
        for ($i=0; $i < count($contacts); $i++) { 
            $prueb[] = $contacts[$i]->getWaId();
        }
        file_put_contents('wa_contacs.json', json_encode($prueb));
        if($rota > 0) {

            $this->waSender->initConmutador();
            if($this->waSender->conm == null) {
                return;
            }

            $sendeds = [];
            for ($i=0; $i < $rota; $i++) { 

                if(in_array($contacts[$i]->getWaId(), $sendeds)) {
                    continue;
                }
                $sendeds[] = $contacts[$i]->getWaId();
                $this->waSender->setWaIdToConmutador($contacts[$i]->getWaId());
                if($contacts[$i]->isLogged()) {
                    $this->waSender->sendPreTemplate($template);
                }else{
                    if(!$this->waSender->fSys->existe('waRemOk', $this->msg->from.'.json')) {
                        $this->waSender->sendTemplateRememberLogin();
                        $this->waSender->fSys->setContent('waRemOk', $this->msg->from.'.json', []);
                    }
                }
            }

            if(count($sendeds) > 0) {
                file_put_contents('wa_enviaods.json', json_encode($sendeds));
                $this->waSender->setWaIdToConmutador($this->msg->from);
                $this->waSender->sendText('🙂👍 Mensaje enviado a la RED con éxito');
            }
        }
    }

    /** */
    private function build() : array
    {
        $body = mb_strtolower($this->msg->content['caption']);
        $partes = explode(' ', $body);
        $rota = count($partes);
        $cuerpo = [];
        
        $id = round(microtime(true) * 1000);
        $idSendFile = 'cmdqc::'.$id.'::'.$this->msg->from;
        $folderToBackup = $this->waSender->fSys->getFolderTo('fbSended');
        $filename = $folderToBackup."/".$idSendFile.'.json';

        for ($i=0; $i < $rota; $i++) { 
            if($partes[$i] == '#') {
                continue;
            }
            if($partes[$i] == '#qc') {
                continue;
            }
            if($partes[$i] == 'qc') {
                continue;
            }
            $cuerpo[] = $partes[$i];
        }

        $body = implode(' ', $cuerpo);
        $msgSended = [
            "id" => $id,
            "title" => "📣 QUIÉN CON❓",
            "body" => $body,
            "ownWaId" => $this->msg->from,
            "idDbSr" => $idSendFile,
            "type" => "cotiza_qc",
            "thubmnail" => $this->msg->content['id'],
            "created" => date('Y-m-d\TH:i:s'),
        ];
        file_put_contents($filename, json_encode($msgSended));

        return [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    "image" => ["id" => $this->msg->content['id']]
                ],
                "body" => [
                    "text" => "📣 QUIÉN CON❓:\n"."🚘 *".trim(mb_strtoupper($body))."*". "\n"
                ],
                "footer" => [
                    "text" => "Si cuentas con la pieza, presiona *Cotizar Ahora*"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => 'cotNowWa_'. $idSendFile,
                                "title" => "[√] COTIZAR AHORA"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
