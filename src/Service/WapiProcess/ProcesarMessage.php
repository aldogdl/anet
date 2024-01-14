<?php

namespace App\Service\WapiProcess;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\WebHook;

use App\Service\WapiProcess\ExtractMessage;
use App\Service\WapiProcess\LoginProcess;
use App\Service\WapiProcess\WrapHttp;
use App\Service\WapiProcess\CotDetallesesProcess;
use App\Service\WapiProcess\CotImagesProcess;
use App\Service\WapiProcess\InteractiveProcess;
use App\Service\WapiProcess\StatusProcess;

class ProcesarMessage {

    private ParameterBagInterface $params;
    private WebHook $whook;
    private WrapHttp $wapiHttp;
    private Filesystem $filesystem;

    /** 
     * Analizamos si el mensaje es parte de una cotizacion
    */
    public function __construct(ParameterBagInterface $container, WebHook $wh, WrapHttp $waS)
    {
        $this->params    = $container;
        $this->whook     = $wh;
        $this->wapiHttp  = $waS;
		$this->filesystem= new Filesystem();
    }

    /** */
    public function execute(array $message, bool $isTest = false): void
    {        
        $obj = new ExtractMessage($message);
        // Esto es solo para desarrollo
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

        $pathChat = $this->getFolderTo('chat');
        $pathConm = $this->params->get('tkwaconm');
        if($obj->isLogin) {
            new LoginProcess($obj->get(), $pathConm, $pathChat, $this->whook, $this->wapiHttp);
            return;
        }
        
        $pathTracking = $this->getFolderTo('tracking');
        if($obj->isStt) {
            new StatusProcess($obj->get(), $pathChat, $pathTracking, $this->whook);
            return;
        }

        $paths = [
            'chat'       => $pathChat,
            'tkwaconm'   => $pathConm,
            'tracking'   => $pathTracking,
            'cotProgres' => $this->getFolderTo('cotProgres'),
            'trackeds'   => $this->getFolderTo('trackeds'),
            'waTemplates'=> $this->params->get('waTemplates'),
            'prodTrack'  => $this->params->get('prodTrack'),
        ];
        if($obj->isInteractive) {
            new InteractiveProcess($obj->get(), $this->whook, $this->wapiHttp, $paths);
            return;
        }

        // Revisamos si hay cotizacion en curso
        $hasCotProgress = false;
        try {
            $cotProgress = file_get_contents($paths['cotProgres'].'/'.$obj->from.'.json');
            if(strlen($cotProgress) > 0) {
                $cotProgress = json_decode($cotProgress, true);
                $hasCotProgress = true;
            }
        } catch (\Throwable $th) {}

        if($hasCotProgress && $cotProgress['espero'] == 'images' && $obj->isImage) {
            new CotImagesProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
        }
        
        // Esto es como un refuerzo por si siguen llegando imagenes cuando se esta esperando detalles
        if($hasCotProgress && $cotProgress['espero'] == 'detalles' && $obj->isImage) {
            new CotImagesProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
        }
        
        if($hasCotProgress && $cotProgress['espero'] == 'detalles' && $obj->isText) {
            new CotDetallesProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
            return;
        }

        if($hasCotProgress && $cotProgress['espero'] == 'costo' && $obj->isText) {
            new CotCostoProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
            return;
        }

        // Si llega aqui es por que la conversion es libre;
        return;
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
