<?php

namespace App\Service;

use App\Enums\TypesWaMsgs;

use App\Service\MyFsys;
use App\Service\Pushes;
use App\Service\ItemTrack\HandlerQuote;
use App\Service\ItemTrack\HandlerCMD;
use App\Service\ItemTrack\HcFinisherCot;
use App\Service\ItemTrack\ParseMsg;
use App\Service\ItemTrack\WaBtnCotNow;
use App\Service\ItemTrack\WaBtnNtgX;
use App\Service\ItemTrack\WaSender;
use App\Service\ItemTrack\WaInitSess;
use App\Service\RasterHub\TrackProv;
use App\Repository\FcmRepository;

class WaConsumer
{
    private WaSender $waSender;
    private MyFsys $fsys;
    private Pushes $push;
    private FcmRepository $fcmEm;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(FcmRepository $fbm, MyFsys $fSys, WaSender $waS, Pushes $push)
    {
        $this->fsys = $fSys;
        $this->fcmEm = $fbm;
        $this->waSender = $waS;
        $this->push = $push;
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
            $t = time();
            file_put_contents('message_'.$t.'.json', json_encode($message));
            file_put_contents('message_process_'.$t.'.json', json_encode($obj->toArray()));
        }

        if($obj->tipoMsg == TypesWaMsgs::STT) {

            // Si no hay un archivo de Stop STT enviamos los STT a RasterX
            if(!$this->fsys->existe('/', $obj->from.'_stopstt.json')) {

                // Tecnica para proveedor a proveedor todos los mensajes
                // enviados se ignoran los status
                if($this->fsys->existe('waSttStop', $obj->from.'.txt')) {
                    $this->fsys->delete('waSttStop', $obj->from.'.txt');
                    return;
                }
                $this->waSender->setConmutador($obj);
                $this->waSender->sendMy($obj->toStt());

            }else{

                // Si es un STT y hay un archivo de Costo, es que acaba de ser
                // finalizada una cotizacion por parte del cotizador.
                if($this->fsys->existe('/', $obj->from.'_scto.json')) {
                    $item = $this->fsys->getContent('tracking', $obj->from.'.json');
                    if(count($item) > 0) {
                        $finicher = new HcFinisherCot($this->waSender, $obj, $item);
                        $finicher->exe('fin');
                    }
                }
            }
            return;

        }elseif ($obj->tipoMsg == TypesWaMsgs::DOC) {

            $this->waSender->setConmutador($obj);
            $this->waSender->sendText(
                "⚠️ Lo sentimos mucho, por el momento este sistema acepta sólo:\n".
                "TEXTO e IMÁGENES.\n".
                "Posiblemente una de las imágenes que enviaste con anterioridad ".
                "resultó ser un video u otro tipo de archivo que no es una Imagen."
            );
            return;

        }elseif ($obj->tipoMsg == TypesWaMsgs::LOGIN) {

            $clase = new WaInitSess($this->fcmEm, $this->fsys, $this->waSender, $this->push, $obj);
            $clase->exe();
            return;

        }elseif ($obj->tipoMsg == TypesWaMsgs::COMMAND) {

            if($this->fsys->existe('tracking', $obj->from.'.json')) {
                $this->waSender->setConmutador($obj);
                $this->waSender->sendText(
                    "⚠️ Lo sentimos mucho, para ejecutar cualquier *COMANDO*, ".
                    "es necesario que no tengas una COTIZACIÓN en CURSO.\n\n".
                    "Termina la cotización y después ejecuta nuevamente este comando."
                );
                return;
            }
            $clase = new HandlerCMD($this->fsys, $this->waSender, $obj);
            $clase->exe();
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::COTPP) {
            $pp = new TrackProv(null, $this->waSender, [], []);
            $pp->sentResponseByAction( $this->fsys->getFolderTo('fbSended'), $obj );
            return;
        }

        // Borramos el archivo de inicio de sesion, ya que ha estas alturas no es necesario
        $this->fsys->delete('/', $obj->from.'_iniLogin.json');
        
        $hasCotProgress = $this->fsys->existe('tracking', $obj->from.'.json');

        if($obj->tipoMsg == TypesWaMsgs::BTNCOTNOW) {

            $clase = new WaBtnCotNow($this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }elseif ($obj->tipoMsg == TypesWaMsgs::NTG || $obj->tipoMsg == TypesWaMsgs::NTGA) {

            $clase = new WaBtnNtgX($this->waSender, $obj);
            $clase->exe($hasCotProgress);
            return;
        }
        
        if($hasCotProgress) {
            
            $handler = new HandlerQuote($this->fsys, $this->waSender, $obj);
            $handler->exe();
            return;
        }elseif ($this->fsys->existe('/', 'conv_free_'.$obj->from.'.json')) {
            
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
