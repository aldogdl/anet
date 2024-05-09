<?php

namespace App\Service\AnetTrack;

use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaInitSess;
use App\Service\AnetTrack\HandlerQuote;
use App\Service\AnetTrack\WaSender;

class Consumer
{
    private Fsys $fSys;
    private WaSender $waSender;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(Fsys $fsys, WaSender $waS)
    {
        $this->fSys = $fsys;
        $this->waSender = $waS;
    }

    /** */
    public function exe(array $message, bool $isTest = false): void
    {
        $parser = new ParseMsg($message);
        $obj = $parser->parse($isTest);
        if($obj == null) {
            // TODO Guardar en el folder de analizar
            return;
        }

        // Esto es solo para desarrollo
        if($obj->tipoMsg != TypesWaMsgs::STT) {
            file_put_contents('message.json', json_encode($message));
            file_put_contents('message_process.json', json_encode($obj->toArray()));
        }

        if($obj->tipoMsg == TypesWaMsgs::STT) {
            if(!$this->fSys->existe('tracking', $obj->from.'.json')) {
                $this->waSender->setConmutador($obj);
                $this->waSender->sendMy($obj->toStt());
            }
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::LOGIN) {
            $clase = new WaInitSess($this->fSys, $this->waSender, $obj);
            $clase->exe();
            return;
        }

        // Borramos el archivo de inicio de sesion, ya que ha estas alturas no es necesario
        $this->fSys->delete('/', $obj->from.'_iniLogin.json');
        
        $this->fSys->setContent('/', 'por_aqui'.$obj->subEvento.'.json', []);
        $hasCotProgress = $this->fSys->existe('tracking', $obj->from.'.json');
        $this->fSys->setContent('/', 'existe_'.$hasCotProgress.'_'.$obj->subEvento.'.json', []);
        if($obj->tipoMsg == TypesWaMsgs::DOC) {
            $this->fSys->setContent('/', 'segui_!'.$obj->subEvento.'.json', []);
            $this->waSender->setConmutador($obj);
            $this->waSender->sendText(
                "Lo sentimos mucho, por el momento este sistema acepta sólo:\n".
                "TEXTO e IMÁGENES."
            );
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::BTNCOTNOW) {
            $this->fSys->setContent('/', 'segui_2'.$obj->subEvento.'.json', []);
            $clase = new WaBtnCotNow($this->fSys, $this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::NTG || $obj->tipoMsg == TypesWaMsgs::NTGA) {
            $this->fSys->setContent('/', 'segui_3'.$obj->subEvento.'.json', []);
            $clase = new WaBtnNtgX($this->fSys, $this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }
        
        $this->fSys->setContent('/', 'segui_4'.$obj->subEvento.'.json', []);
        if($hasCotProgress) {
            $this->fSys->setContent('/', 'manejador'.$obj->subEvento.'.json', []);
            $handler = new HandlerQuote($this->fSys, $this->waSender, $obj);
            $handler->exe();
            return;
        }elseif ($this->fSys->existe('/', 'conv_free_'.$obj->from.'.json')) {
            
            // dd('Hay conversacion libre');
            $this->fSys->setContent('/', 'rev_cot_free_'.$obj->subEvento.'.json', []);
        }
        $this->fSys->setContent('/', 'me_voy_'.$obj->subEvento.'.json', []);
        return;
    }

}
