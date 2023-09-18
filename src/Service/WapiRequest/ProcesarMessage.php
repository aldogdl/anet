<?php

namespace App\Service\WapiRequest;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\WebHook;
use App\Service\WapiRequest\IsLoginMessage;
use App\Service\WapiRequest\ExtractMessage;
use App\Service\WapiRequest\IsInteractiveMessage;
use App\Service\WapiRequest\IsCotizacionMessage;
use App\Service\WapiResponse\ConmutadorWa;
use App\Service\WapiResponse\CostoProcess;
use App\Service\WapiResponse\DetallesProcess;
use App\Service\WapiResponse\FotosProcess;
use App\Service\WapiResponse\LoginProcess;
use App\Service\WapiResponse\WrapHttp;

class ProcesarMessage {

    private $params;

    private array $message;
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
        $result = [];

        $obj = new ExtractMessage($message);

        if($obj->pathToAnalizar != '') {
            $folder = $this->getFolderTo('waAnalizar');
            $this->saveFile($folder.'/'.$obj->pathToAnalizar, $message);
            return;
        }
        
        $this->message = $obj->get();
        if($obj->isStt) {
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
            return;
        }
        
        $conm = new ConmutadorWa($this->message, $this->params->get('tkwaconm'));
        $isInteractive = new IsInteractiveMessage($this->message);
        $cotTransit = new IsCotizacionMessage($this->message, $this->params->get('waCots'));
        if($cotTransit->inTransit) {

            $fileCot = $cotTransit->getCotizacionInTransit();

            switch ($fileCot['current']) {

                case 'fotos':

                    $obj = new FotosProcess($cotTransit->pathFull);
                    if( $isInteractive->isCot || $isInteractive->isNtg ) {
                        $conm->setBody('text', $obj->getMessageError('replyBtn', $fileCot));
                        $result = $this->wapiHttp->send($conm, true);
                        return;
                    }

                    $isValid = $obj->isValid($this->message, $fileCot);
                    if($isValid != '') {
                        if(!$isInteractive->noFto) {
                            $conm->setBody('interactive', $obj->getMessageError($isValid, $fileCot));
                            $result = $this->wapiHttp->send($conm, true);
                            return;
                        }
                    }

                    // Cambiamos a detalles
                    $fileCot = $cotTransit->updateStepCotizacionInTransit(1, $fileCot);
                    $obj = new DetallesProcess($cotTransit->pathFull);
                    $conm->setBody('interactive', $obj->getMessage($fileCot));
                    $result = $this->wapiHttp->send($conm, true);

                    $this->message = $obj->buildResponse($this->message, $conm->toArray());
                    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
                    break;

                case 'detalles':

                    $obj = new DetallesProcess($cotTransit->pathFull);
                    if( $isInteractive->isCot || $isInteractive->isNtg ) {
                        $conm->setBody('text', $obj->getMessageError('replyBtn', $fileCot));
                        $result = $this->wapiHttp->send($conm, true);
                        return;
                    }

                    $validar = true;
                    if($isInteractive->asNew) {
                        $validar = false;
                    }
                    if($isInteractive->normal) {
                        $validar = false;
                    }

                    $isValid = $obj->isValid($this->message, $fileCot, $validar);
                    if($isValid != '') {

                        if($isValid == 'image') {
                            $this->message = $obj->buildResponse($this->message, []);
                            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
                            return;
                        }

                        if($isValid == 'notFotosReply') {
                            $obj = new FotosProcess($cotTransit->pathFull);
                        }
                        $conm->setBody('text', $obj->getMessageError($isValid, $fileCot));
                        $result = $this->wapiHttp->send($conm, true);
                        return;
                    }

                    // Cambiamos a costo
                    $fileCot = $cotTransit->updateStepCotizacionInTransit(2, $fileCot);
                    $obj = new CostoProcess($cotTransit->pathFull);
                    $conm->setBody('text', $obj->getMessage($fileCot));
                    $result = $this->wapiHttp->send($conm, true);

                    $this->message = $obj->buildResponse($this->message, $conm->toArray());
                    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
                    break;

                case 'costo':

                    $obj = new CostoProcess($cotTransit->pathFull);
                    if( $isInteractive->isCot || $isInteractive->isNtg ) {
                        $conm->setBody('text', $obj->getMessageError('replyBtn', $fileCot));
                        $result = $this->wapiHttp->send($conm, true);
                        return;
                    }

                    $isValid = $obj->isValid($this->message, $fileCot);
                    if($isValid != '') {
                        $conm->setBody('text', $obj->getMessageError($isValid, $fileCot));
                        $result = $this->wapiHttp->send($conm, true);
                        return;
                    }

                    // Cambiamos a Gracias
                    $cotTransit->finishCotizacionInTransit();
                    $conm->setBody('text', $obj->getMessageGrax($fileCot));
                    $result = $this->wapiHttp->send($conm, true);

                    $this->message = $obj->buildResponse($this->message, $conm->toArray(), true);
                    $this->whook->sendMy('wa-wh', 'notSave', $this->message);
                    break;
                
                default:
                    # code...
                    break;
            }
            return;
        }

        if($isInteractive->isCot) {
            
            $cotTransit->setStepCotizacionInTransit(0);
            $obj = new FotosProcess($cotTransit->pathFull);

            $conm->setBody('text', $obj->getMessage());
            $result = $this->wapiHttp->send($conm);

            $this->message = $obj->buildResponse($this->message, $conm->toArray());
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
            return;
        }

        if($isInteractive->isNtg) {
            $this->message['subEvento'] = 'ntg';
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
            return;
        }

        $filename = round(microtime(true) * 1000) .'.json';
        $pathBackup = $this->getFolderTo('waBackup');
        $fileServer = $pathBackup.'/'.$filename;

        $obj = new IsLoginMessage($this->message);
        if($obj->isLogin) {

            $obj = new LoginProcess($this->message, $this->filesystem);
            if($obj->hasErr == '') {
                if(array_key_exists('from', $this->message)) {

                    $conm->setBody('text', $obj->toWhatsapp);
                    $message['response'] = $conm->toArray();
                    $result = $this->wapiHttp->send($conm);
                    
                    $this->message['subEvento'] = 'iniLogin';
                    $this->message['response']  = [
                        'type' => $message['response']['type'],
                        'body' => $message['response']['body']
                    ];
                    $this->whook->sendMy('wa-wh', $fileServer, $this->message);
                }
            }
            return;
        }

        if(count($result) > 0) {
            // Si hay errores los enviamos a backCore para su analisis;
            $message = [
                'error' => $result,
                'data'  => $message
            ];
            $this->whook->sendMy('wa-wh-err', $fileServer, $message);
        }

        $this->saveFile($fileServer, $message);
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
        $path = Path::canonicalize($this->params->get('waBackup'));
        if(!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        return $path;
    }
}