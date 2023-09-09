<?php

namespace App\Service\WapiRequest;

use Symfony\Component\Finder\Finder;
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

        if($obj->isStt) {
            $this->message['subEvento'] = 'stt';
            $this->whook->sendMy('wa-wh', 'notSave', $this->message);
            return;
        }

        $filename = round(microtime(true) * 1000) .'.json';
        $pathBackup = $this->getFolderTo('waBackup');
        $fileServer = $pathBackup.'/'.$filename;
        
        $this->message = $obj->get();
        
        $obj = new IsInteractiveMessage($this->message);
        if($obj->isNtg) {
            $this->message['subEvento'] = 'ntg';
            return;
        }

        if($obj->isCot) {
            $this->message['subEvento'] = 'initCoti';
            return;
        }

        $conm = new ConmutadorWa($this->message, $this->params->get('tkwaconm'));

        $obj = new IsCotizacionMessage($this->message);
        if($obj->inTransit) {
            $this->message['subEvento'] = 'xxx';
            return;
        }

        $obj = new IsLoginMessage($this->message);
        if($obj->isLogin) {

            $this->message['subEvento'] = 'iniLogin';
            $this->whook->sendMy('wa-wh', $fileServer, $this->message);
            
            $obj = new LoginProcess($this->message, $this->filesystem);
            if($obj->hasErr == '') {

                if(array_key_exists('from', $this->message)) {
                    $conm->setBody('text', $obj->toWhatsapp);
                    $message['response'] = $conm->toArray();
                    $result = $this->wapiHttp->send($conm);
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