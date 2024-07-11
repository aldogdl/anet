<?php

namespace App\Service\AnetTrack;

use App\Enums\TypesWaMsgs;
use App\Service\AnetTrack\Fsys;
use App\Service\AnetTrack\WaInitSess;
use App\Service\AnetTrack\HandlerQuote;
use App\Service\AnetTrack\HandlerCMD;
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

        // // Esto es solo para desarrollo
        if($obj->tipoMsg != TypesWaMsgs::STT) {
            file_put_contents('message_'.time().'.json', json_encode($message));
            file_put_contents('message_process_1.json', json_encode($obj->toArray()));
        }

        if($obj->tipoMsg == TypesWaMsgs::STT) {
            // Si no hay un archivo de cotizacion enviamos los STT a EventCore
            if(!$this->fSys->existe('/', $obj->from.'_stopstt.json')) {
                $this->waSender->setConmutador($obj);
                $this->waSender->sendMy($obj->toStt());
            }else{
                // Si es un STT y hay un archivo de Costo, es que acaba de ser
                // finalizada una cotizacion por parte del cotizador.
                if($this->fSys->existe('/', $obj->from.'_scto.json')) {
                    $bait = $this->fSys->getContent('tracking', $obj->from.'.json');
                    if(count($bait) > 0) {
                        $finicher = new HcFinisherCot($this->waSender, $obj, $bait);
                        $finicher->exe('fin');
                    }
                }
            }
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::DOC) {

            $this->waSender->setConmutador($obj);
            $this->waSender->sendText(
                "⚠️ Lo sentimos mucho, por el momento este sistema acepta sólo:\n".
                "TEXTO e IMÁGENES."
            );
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::LOGIN) {

            $clase = new WaInitSess($this->fSys, $this->waSender, $obj);
            $clase->exe();
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::COMMAND) {

            if($this->fSys->existe('tracking', $obj->from.'.json')) {
                $this->waSender->setConmutador($obj);
                $this->waSender->sendText(
                    "⚠️ Lo sentimos mucho, para ejecutar cualquier *COMANDO*, ".
                    "es necesario que no tengas una COTIZACIÓN en CURSO.\n\n".
                    "Termina la cotización y después ejecuta nuevamente este comando."
                );
                return;
            }
            $clase = new HandlerCMD($this->fSys, $this->waSender, $obj);
            $clase->exe();
            return;
        }

        // Borramos el archivo de inicio de sesion, ya que ha estas alturas no es necesario
        $this->fSys->delete('/', $obj->from.'_iniLogin.json');
        
        $hasCotProgress = $this->fSys->existe('tracking', $obj->from.'.json');

        if ($obj->tipoMsg == TypesWaMsgs::BTNCOTNOW) {

            $clase = new WaBtnCotNow($this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::NTG || $obj->tipoMsg == TypesWaMsgs::NTGA) {

            $clase = new WaBtnNtgX($this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }
        
        if($hasCotProgress) {
            
            $handler = new HandlerQuote($this->fSys, $this->waSender, $obj);
            $handler->exe();
            return;
        }elseif ($this->fSys->existe('/', 'conv_free_'.$obj->from.'.json')) {
            
            // dd('Hay conversacion libre');
        }

        $this->waSender->setConmutador($obj);
        $this->waSender->sendText(
            "😱 *¡LO SENTIMOS!*\n".
            "_Para interactuar con este sistema debes seleccionar uno de los botones de alguna solicitud_.\n\n".
            "*Por ejemplo*, presiona el botón:\n*COTIZAR AHORA*."
        );
        return;
    }
    
}
