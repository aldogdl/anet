<?php

namespace App\Service\WapiRequest;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\WebHook;

use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\FotosProcess;
use App\Service\WapiResponse\LoginProcess;
use App\Service\WapiResponse\WrapHttp;

use App\Service\WapiRequest\IsLoginMessage;
use App\Service\WapiRequest\ExtractMessage;
use App\Service\WapiRequest\IsInteractiveMessage;
use App\Service\WapiRequest\IsCotizacionMessage;

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
    public function execute(array $message): void
    {
        $obj = new ExtractMessage($message);
        if($obj->pathToAnalizar != '') {
            $folder = $this->getFolderTo('waAnalizar');
            $this->saveFile($folder.'/'.$obj->pathToAnalizar, $message);
            return;
        }
        
        if($obj->isStt) {
            $this->whook->sendMy('wa-wh', 'notSave', $obj->get());
            return;
        }

        $msg = $obj->get();
        $obj = new IsCotizacionMessage($msg, $this->params->get('waCots'));
        if($obj->inTransit) {
            $this->processCotInTransit($msg, $obj);
            return;
        }
        
        $isInteractive = new IsInteractiveMessage($msg);
        if($isInteractive->isNtg) {
            $msg['subEvento'] = 'ntg';
            $this->whook->sendMy('wa-wh', 'notSave', $msg);
            return;
        }

        if($isInteractive->isCot) {
            
            $obj->setStepCotizacionInTransit(0);
            $obj = new FotosProcess($obj->pathFull);
            
            $conm = new ConmutadorWa($msg, $this->params->get('tkwaconm'));
            $conm->setBody('text', $obj->getMessage());
            $this->wapiHttp->send($conm);

            $msg = $obj->buildResponse($msg, $conm->toArray());
            $this->whook->sendMy('wa-wh', 'notSave', $msg);
            return;
        }

        $obj = new IsLoginMessage($msg);
        if($obj->isLogin) {
            $this->processMsgOfLogin($msg);
            return;
        }
    }

    /** 
     * Aqui procesamos las cotizaciones que estan en transito
    */
    private function processCotInTransit(array $msg, IsCotizacionMessage $cotTransit) {

        $conm = new ConmutadorWa($msg, $this->params->get('tkwaconm'));
        $isInteractive = new IsInteractiveMessage($msg);
        $fileCot = $cotTransit->getCotizacionInTransit();
            
        if( $isInteractive->isCot || $isInteractive->isNtg ) {
            $conm->setBody('text', $cotTransit->getMsgErrorOtraCot($fileCot));
            $this->wapiHttp->send($conm, true);
            return;
        }

        switch ($fileCot['current']) {

            case 'fotos':
                $fto = new ReqFotosProcess($msg, $conm, $cotTransit, $this->wapiHttp, $this->whook);
                $fto->exe($isInteractive->noFto);
                break;
            case 'detalles':
                $det = new ReqDetallesProcess($msg, $conm, $cotTransit, $this->wapiHttp, $this->whook);
                $det->exe($isInteractive->asNew, $isInteractive->normal);
                break;
            case 'costo':
                $cto = new ReqCostoProcess($msg, $conm, $cotTransit, $this->wapiHttp, $this->whook);
                $cto->exe($isInteractive->noFto);
                break;
            default:
        }
    }

    /**
     * Aqui procesamos los mensajes de login
    */
    private function processMsgOfLogin(array $msg) {

        $obj = new LoginProcess($msg, $this->filesystem);
        if($obj->hasErr == '') {
            if(array_key_exists('from', $msg)) {

                $conm = new ConmutadorWa($msg, $this->params->get('tkwaconm'));
                $conm->setBody('text', $obj->toWhatsapp);
                $message['response'] = $conm->toArray();
                $result = $this->wapiHttp->send($conm);
                
                $msg['subEvento'] = 'iniLogin';
                $msg['response']  = [
                    'type' => $message['response']['type'],
                    'body' => $message['response']['body']
                ];
                $this->whook->sendMy('wa-wh', 'notSave', $msg);
            }
        }
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
