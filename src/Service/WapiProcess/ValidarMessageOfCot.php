<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\WapiProcess\WrapHttp;

class ValidarMessageOfCot {

    public String $hasErr = '';
    public int $code = 100;
    public bool $isValid = false;
    
    public array $paths = [];
    public array $cotProgress = [];
    private Filesystem $filesystem;
    private WrapHttp $wapiHttp;

    /** 
     * Analizamos los mensajes para detectar errores, y como se condiciona cada
     * mensaje para determinar su tipo, evitamos hacerlo doble con la variable $code
    */
    public function __construct(
        ExtractMessage $obj, WrapHttp $wapi, array $paths, array $cotProgress
    ){

        $this->isValid  = true;

        $this->paths       = $paths;
        $this->cotProgress = $cotProgress;
        $this->wapiHttp = $wapi;
        $this->filesystem = new Filesystem();

        if($obj->isInteractive) {
            $this->validateInteractive($obj->get());
            return;
        }

        $this->code = 101;
        if($cotProgress['current'] == 'sfto' && $obj->isImage) {
            $this->validateImage($obj->get());
            return;
        }
        
        if($cotProgress['current'] == 'sdta' && $obj->isImage) {
            $this->validateImage($obj->get(), 'deep');
            return;
        }
        
        $this->code = 102;
        if($cotProgress['current'] == 'sdta' && $obj->isText) {
            return;
        }
        
        if($cotProgress['current'] == 'scto' && $obj->isText) {
            return;
        }
    }

    /** */
    private function validateInteractive(WaMsgMdl $msg): void
    {
        // El mensaje recibido es que... No agregará fotos
        if($msg->subEvento == 'nfto') {
            // Enviamos el mensaje de confirmacion de sin fotos
            $template = $this->getFile($msg->subEvento.'.json');
            $deco = new DecodeTemplate($this->cotProgress);
            $template = $deco->decode($template);
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }
        
        // El mensaje recibido es que... Que se arrepintio y agregara fotos
        if($msg->subEvento == 'sifto') {
            // Enviamos el mensaje de buena elenccion agrega fotos
            $template = $this->getFile($msg->subEvento.'.json');
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }
    }

    /** */
    private function validateImage(WaMsgMdl $msg, String $type = 'basic'): void
    {
        $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
        if(!in_array($msg->status, $permitidas)) {
            $template = $this->getFile('eftoExt.json');
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }

        // Si es deep viene de la condicion dende ya se envio el msg de detalles pero sigue
        // enviando fotos, por lo tanto, es necesario calcular si hay que enviarle otro msg
        // para recordarle en que paso va (detalles)
        if($type == 'deep') {
            $cant = 0;
            if(array_key_exists('fotos', $this->cotProgress['track'])) {
                $cant = count($this->cotProgress['track']['fotos']) + 1;
            }
            if($cant > 2) {
                if (($cant % 2) != 0) {
                    $template = [
                        'type' => 'text',
                        'text' => [
                            'preview_url' => false,
                            'body' => '*Hemos recibido '.$cant.' fotografías*.\n\n📝Al finalizar de enviar fotos, puedes continuar con los detalles de la pieza.'
                        ]
                    ];
                    $this->sentMsg($template, $msg->from);
                    return;
                }
            }
        }
    }

    /** */
    private function validateX(WaMsgMdl $msg): void
    {
        // if($message->type != 'text') {
        //     // TODO enviar error al cliente
        //     return;
        // }
        // $isValid = $this->isValid($current, $message->message);
        // if(!$isValid) {
        //     return;
        // }
    }
    
    /** */
    private function isValidX(String $campo, String $data): bool
    {   
        if($campo == 'sdta') {
            if(strlen($campo) < 3) {
                // TODO enviar error al cliente
                return false;
            }
        }

        if($campo == 'scto') {
            if(strlen($campo) < 3) {
                // TODO enviar error al cliente
                return false;
            }
        }
        return true;
    }

    /**
     * Solo es necesario revisar el costo para saber si estan enviando un numero
     * o letras para indicar este valor.
     */
    public function isValidNumero(String $data) : bool
    {
        $result = $data;
        $str = mb_strtolower($result);
        if(mb_strpos($str, 'mil') !== false) {
            return false;
        }

        $str = str_replace('$', '', $str);
        $str = str_replace(',', '', $str);

        if(mb_strpos($str, '.') !== false) {

            $partes = explode('.', $str);
            $entera = $this->isDigit($partes[0]);
            if($entera != '-1') {
                $decimal = $this->isDigit($partes[1]);
                if($decimal != '-1') {
                    $result = $entera.'.'.$decimal;
                    return true;
                }
            }
        }

        $entera = $this->isDigit($str);
        if($entera != '-1') {
            $result = $entera.'.00';
            return true;
        }
        return false; 
    }

    /**
     * Checamos si el valor dado es un numero.
     */
    private function isDigit(String $value) : String
    {
        $value = preg_replace('/[^0-9]/', '', $value);
        if(strlen($value) > 2) {
            if(is_int($value) || ctype_digit($value)) {
                return $value;
            }
        }
        return '-1';
    }

    /** */
    private function sentMsg(array $template, String $to, bool $withContext = false)
    {
        if(count($template) > 0) {

            if($withContext) {
                if(array_key_exists('wamid_cot', $this->cotProgress)) {
                    $template['context'] = $this->cotProgress['wamid_cot'];
                }
            }
            $conm = new ConmutadorWa($to, $this->paths[1]);
            $conm->setBody($template['type'], $template);
            $result = $this->wapiHttp->send($conm);
            if($result['statuscode'] != 200) {
                // TODO Hacer archivo de registros de errores
                return;
            }
        }
    }

    /** */
    private function getFile(String $filename): array
    {
        $path = $this->paths[0].'/'.$filename;
        if($this->filesystem->exists($path)) {
            try {
                $tpl = file_get_contents($path);
                if(strlen($tpl) > 0) {
                    return json_decode($tpl, true);
                }
            } catch (\Throwable $th) {}
        }
        // TODO tener un msg template de error desconocido
        return [];
    }

}