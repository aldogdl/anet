<?php

namespace App\Service\WapiProcess;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\WebHook;

use App\Service\WapiProcess\WrapHttp;
use App\Service\WapiProcess\ExtractMessage;
use App\Service\WapiProcess\LoginProcess;
use App\Service\WapiProcess\StatusProcess;
use App\Service\WapiProcess\CotTextProcess;
use App\Service\WapiProcess\CotImagesProcess;
use App\Service\WapiProcess\InteractiveProcess;

class ProcesarMessage {

    private ParameterBagInterface $params;
    private WebHook $whook;
    private WrapHttp $wapiHttp;
    private Filesystem $filesystem;
    private bool $hasCotProgress;

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
        if($obj->pathToAnalizar != '') {
            $folder = $this->getFolderTo('waAnalizar');
            $this->saveFile($folder.$obj->pathToAnalizar, $message);
            // TODO Enviar a EventCore
            return;
        }

        // Revisamos si hay cotizacion en curso
        $this->hasCotProgress  = false;
        $pathCotProgress = $this->getFolderTo('cotProgres');
        $cotProgress     = $this->getFileCotProgress($pathCotProgress, $obj->from.'.json');

        // Esto es solo para desarrollo
        if(!$obj->isStt) {
            file_put_contents('message.json', json_encode($message));
            file_put_contents('message_process.json', json_encode($obj->get()->toArray()));
        }

        $paths = [
            'tkwaconm'   => $this->params->get('tkwaconm'),
            'waTemplates'=> $this->params->get('waTemplates'),
            'prodTrack'  => $this->params->get('prodTrack'),
            'tracking'   => $this->getFolderTo('tracking'),
            'trackeds'   => $this->getFolderTo('trackeds'),
            'cotProgres' => $pathCotProgress,
            'hasCotPro'  => $this->hasCotProgress
        ];
        if($obj->isLogin) {
            new LoginProcess($obj->get(), $paths, $this->whook, $this->wapiHttp);
            return;
        }

        if($obj->isStt) {
            // No procesamos los status cuando se esta cotizando
            if(!$this->hasCotProgress) {
                $this->whook->sendMy('wa-wh', 'notSave', ['recibido' => $obj->get()->toArray()]);
            }
            return;
        }

        // Todo mensaje que llega a esta altura debe ser una cotizacion en progreso
        // Toda Cot en Progreso, debe contener el IdItem
        $code = 100;
        $validator = new ValidarMessageOfCot($obj, $this->wapiHttp, $paths, $cotProgress);
        $validator->validate();
        if(!$validator->isValid) { return; }

        if(count($cotProgress) == 0 && count($validator->cotProgress) > 0) {
            $cotProgress = $validator->cotProgress;
            $paths['hasCotPro'] = true;
        }
        $code = $validator->code;
        $validator = null;
        
        switch ($code) {
            case 100:
                // Si presionó COTIZAR AHORA, se creo el archivo [cotProgress]
                $int = new InteractiveProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
                $int->exe();
                break;
            case 101:
                $img = new CotImagesProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
                $img->exe();
                break;
            case 102:
                $txt = new CotTextProcess($obj->get(), $this->whook, $this->wapiHttp, $paths, $cotProgress);
                $txt->exe();
                break;
            default:
                # code...
                break;
        }

        // Si llega aqui es por que la conversion es libre;
        return;
    }

    /** 
     * Revisamos para ver si existe un archivo de cotizacion en curso del cotizador
    */
    private function getFileCotProgress(String $pathCotProgress, String $filename): array
    {
        $path = $pathCotProgress.'/'.$filename;
        if($this->filesystem->exists($path)) {
            try {
                $cotProgress = file_get_contents($path);
                if(strlen($cotProgress) > 0) {
                    $this->hasCotProgress = true;
                    return json_decode($cotProgress, true);
                }
            } catch (\Throwable $th) {}
        }
        return [];
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
