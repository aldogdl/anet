<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;

class SendQC
{
    private WaSender $waSender;
    private WaMsgDto $msg;

    /** */
    public function __construct(WaSender $waS)
    {
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

        $contacts = [];
        $rota = 0;

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
                    try {
                        $this->waSender->sendPreTemplate($template);
                    } catch (\Throwable $th) {
                        continue;
                    }
                }else{
                    if(!$this->waSender->fSys->existe('waRemOk', $this->msg->from.'.json')) {
                        try {
                            $this->waSender->sendTemplateRememberLogin();
                            $this->waSender->fSys->setContent('waRemOk', $this->msg->from.'.json', []);
                        } catch (\Throwable $th) {
                            continue;
                        }
                    }
                }
            }

            if(count($sendeds) > 0) {
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
            "id"        => $id,
            "title"     => "📣 QUIÉN CON❓",
            "body"      => $body,
            "ownWaId"   => $this->msg->from,
            "idDbSr"    => $idSendFile,
            "type"      => "cotiza_qc",
            "thubmnail" => $this->msg->content['id'],
            "created"   => date('Y-m-d\TH:i:s'),
        ];
        file_put_contents($filename, json_encode($msgSended));
        
        $tm = new TemplatesTrack();
        return $tm->forTrackOnlyBtnCotizar($this->msg->content['id'], $body, $idSendFile);
    }

}
