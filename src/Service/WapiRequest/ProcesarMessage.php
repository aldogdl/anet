<?php

namespace App\Service\WapiRequest;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\WebHook;

use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\LoginProcess;
use App\Service\WapiResponse\WrapHttp;

use App\Service\WapiRequest\ExtractMessage;
use App\Service\WapiRequest\IsCotizacionMessage;
use App\Service\WapiResponse\InteractiveProcess;
use App\Service\WapiResponse\StatusProcess;

class ProcesarMessage {

    private $params;
    private WebHook $whook;
    private WrapHttp $wapiHttp;
    private Filesystem $filesystem;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(ParameterBagInterface $container, WebHook $wh, WrapHttp $waS)
    {
        $this->params = $container;
        $this->whook = $wh;
        $this->wapiHttp = $waS;
		$this->filesystem = new Filesystem();
    }

    /** */
    public function execute(array $message, bool $isTest = false): void
    {
        $pathTracking = $this->params->get('tracking');
        
        $obj = new ExtractMessage($message);
        if(!$obj->isStt) {
            file_put_contents('message.json', json_encode($message));
            file_put_contents('message_process.json', json_encode($obj->get()->toArray()));
        }

        if($obj->pathToAnalizar != '') {
            $folder = $this->getFolderTo('waAnalizar');
            $this->saveFile($folder.$obj->pathToAnalizar, $message);
            // TODO Enviar a EventCore
            return;
        }

        if($obj->isLogin) {
            new LoginProcess(
                $obj->get(), $this->params->get('tkwaconm'), $this->params->get('chat'),
                $this->whook, $this->wapiHttp
            );
            return;
        }
        
        if($obj->isStt) {
            new StatusProcess($obj->get(), $this->params->get('chat'), $pathTracking, $this->whook);
            return;
        }

        if($obj->isInteractive) {
            $paths = [
                'chat'       => $this->params->get('chat'),
                'tkwaconm'   => $this->params->get('tkwaconm'),
                'waTemplates'=> $this->params->get('waTemplates'),
                'prodTrack'  => $this->params->get('prodTrack'),
                'tracking'   => $pathTracking,
                'trackeds'   => $this->params->get('trackeds'),
            ];
            new InteractiveProcess($obj->get(), $paths, $this->whook, $this->wapiHttp);
            return;
        }

        // Si el mensaje no es ningun tipo de los anteriores entonces es un text
        // hay que procesarlo para saber si es parte de una cotizacion en curso o un texto libre.
        
    }

    /** */
    public function executeOld(array $message): void
    {
        $obj = new ExtractMessage($message);

        $filename = 'conv_free.'.$obj->from.'.cnv';
        if(is_file($filename)) {
            $this->whook->sendMy('convFree', 'notSave', $obj->get()->toArray());
            return;
        }

        if($obj->pathToAnalizar != '') {
            $folder = $this->getFolderTo('waAnalizar');
            $this->saveFile($folder.$obj->pathToAnalizar, $message);
            return;
        }
        
        if($obj->isStt) {
            $this->whook->sendMy('wa-wh', 'notSave', $obj->get()->toArray());
            return;
        }

        $msg = $obj->get()->toArray();
        $obj = new IsCotizacionMessage($msg, $this->params->get('waCots'));
        if($obj->inTransit) {
            $this->processCotInTransit($msg, $obj);
            return;
        }
        
        // $isInteractive = new IsInteractiveMessage($msg);
        // if($isInteractive->isNtg) {
        //     $msg['subEvento'] = 'ntg';
        //     $this->whook->sendMy('wa-wh', 'notSave', $msg);
        //     return;
        // }

        // if($isInteractive->isCot) {
        //     $obj = new ReqFotosProcess(
        //         $msg, new ConmutadorWa($msg['from'], $this->params->get('tkwaconm')),
        //         $obj, $this->wapiHttp, $this->whook
        //     );
        //     $obj->initializaCot();
        //     return;
        // }
    }

    /** 
     * Aqui procesamos las cotizaciones que estan en transito
    */
    private function processCotInTransit(array $msg, IsCotizacionMessage $cotTransit) {

        $path = $this->params->get('tkwaconm');
        $fileCot = $cotTransit->getCotizacionInTransit();
        
        // $isInteractive = new IsInteractiveMessage($msg);
        // if( $isInteractive->isCot || $isInteractive->isNtg ) {
        //     $conm = new ConmutadorWa($msg['from'], $path);
        //     $conm->setBody('text', $cotTransit->getMsgErrorOtraCot($fileCot));
        //     $this->wapiHttp->send($conm, true);
        //     return;
        // }

        // switch ($fileCot['current']) {

        //     case 'fotos':
        //         $fto = new ReqFotosProcess(
        //             $msg, new ConmutadorWa($msg['from'], $path),
        //             $cotTransit, $this->wapiHttp, $this->whook
        //         );
        //         $fto->exe($isInteractive->noFto);
        //         break;

        //     case 'detalles':

        //         $det = new ReqDetallesProcess(
        //             $msg, new ConmutadorWa($msg['from'], $path),
        //             $cotTransit, $this->wapiHttp, $this->whook
        //         );
        //         $det->exe($isInteractive->good, $isInteractive->normal, $isInteractive->reparada);
        //         break;

        //     case 'costo':

        //         $cto = new ReqCostoProcess(
        //             $msg, new ConmutadorWa($msg['from'], $path),
        //             $cotTransit, $this->wapiHttp, $this->whook
        //         );
        //         $cto->exe();
        //         break;

        //     default:
        // }
    }

    /** */
    private function saveFile(String $path, array $content) {

		try {
			$this->filesystem->dumpFile($path, json_encode($content));
		} catch (FileException $e) {
			$path = 'Error' . $e->getMessage();
		}
    }

    /** */
    private function getFolderTo(String $folder): String
    {
        $path = Path::canonicalize($this->params->get($folder));
        if(!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        return $path;
    }

}
