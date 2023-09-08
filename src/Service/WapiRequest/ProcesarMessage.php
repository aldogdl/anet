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
        if($obj->isStt) {

            return;
        }

        $filename = round(microtime(true) * 1000) .'.json';
        $pathBackup = $this->getFolderTo('waBackup');
        $fileServer = $pathBackup.'/'.$filename;
        
        $this->message = $obj->get();
        
        $obj = new IsInteractiveMessage($this->message);
        if($obj->isNtg) {

            return;
        }

        if($obj->isCot) {

            return;
        }

        $token = file_get_contents($this->params->get('waTk'));
        $obj = new IsCotizacionMessage($this->message);
        if($obj->inTransit) {
            return;
        }

        $obj = new IsLoginMessage($this->message);
        if($obj->isLogin) {

            $this->whook->sendMy('wa-wh', $fileServer, $this->message);

            $obj = new LoginProcess($this->message, $this->filesystem);
            if($obj->hasErr == '') {
                if(array_key_exists('from', $this->message)) {
                    $this->wapiHttp->wrapBody($message['from'], 'text', $obj->toWhatsapp);
                    $message['response'] = $obj->toWhatsapp;
                    $result = $this->wapiHttp->send($token);
                }
            }
            return;
        }

        if(count($result) > 0) {
            // Si hay errores los enviamos a backCore para su analisis;
            $data = [
                'error' => $result,
                'data'  => $message
            ];
            $this->whook->sendMy('wa-wh-err', $fileServer, $data);
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