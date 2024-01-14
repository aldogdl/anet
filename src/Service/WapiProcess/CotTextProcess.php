<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;
use App\Service\WebHook;
use App\Service\WapiProcess\WrapHttp;

class CotTextProcess
{

    public String $hasErr = '';
    private String $result;
    private array $cotProgress;
    private array $msgs = [
        'sdta' => ['current' => 'scto', 'next' => 'sgrx'],
        'scto' => ['current' => 'sgrx', 'next' => 'sfto'],
    ];

    /** 
     * 
    */
    public function __construct(
        WaMsgMdl $message, WebHook $wh, WrapHttp $wapiHttp, array $paths, array $cotProgress
    ){
        if($message->type != 'text') {
            // TODO enviar error al cliente
            return;
        }
        $current = $cotProgress['current'];
        $message->subEvento = $current;
        $isValid = $this->isValid($current, $message->message);
        if(!$isValid) {
            return;
        }

        $this->cotProgress = $cotProgress;
        $cotProgress = [];

        $campo = ($current == 'sdta') ? 'detalles' : 'precio';
        // Actualizar el trackFile para el siguiente mensaje y contenido de cotizacion
        $this->cotProgress['current'] = $this->msgs[$current]['current'];
        $this->cotProgress['next']    = $this->msgs[$current]['next'];
        $this->cotProgress['track'][$campo] = $message->message;

        $fSys = new FsysProcess($paths['cotProgres']);
        // Guardamos inmediatamente el cotProgess para evitar enviar los detalles nuevamente.
        if($current == 'sdta') {
            $fSys->setContent($message->from.'.json', $this->cotProgress);
        }else {
            // Si ya es el costo borramos el mensaje
            $fSys->delete($message->from.'.json');
        }
        
        $sended = [];
        $entroToSended = false;
        // Respondemos inmediatamente a este boton interativo con el mensaje adecuado
        $fSys->setPathBase($paths['waTemplates']);
        $template = $fSys->getContent($this->cotProgress['current'].'.json');
        
        // Revisamos si existe el id del contexto de la cotizacion para agregarlo al msg de respuesta
        if($current == 'sdta') {
            if(array_key_exists('wamid_cot', $this->cotProgress)) {
                $template['context'] = $this->cotProgress['wamid_cot'];
            }
        }

        $typeMsgToSent = 'text';
        $conm = new ConmutadorWa($message->from, $paths['tkwaconm']);
        if(count($template) > 0) {

            $conm->setBody($typeMsgToSent, $template);
            $result = $wapiHttp->send($conm);
            if($result['statuscode'] != 200) {
                $wh->sendMy('wa-wh', 'notSave', $result);
                return;
            }

            // Extraemos el IdItem del mensaje que se va a enviar al cotizador cuando se
            // responde con otro mensaje interactivo
            $idItem = '0';
            if(array_key_exists('action', $template)) {
                if(array_key_exists('buttons', $template['action'])) {
                    $idItem = $template['action']['buttons'][0]['reply']['id'];
                    $partes = explode('_', $idItem);
                    $idItem = $partes[1];
                }
                $conm->bodyRaw = ['body' => $template['body'], 'idItem' => $idItem];
            }else{
                $conm->bodyRaw = $template['body'];
            }

            $objMdl = $conm->setIdToMsgSended($message, $result);
            $sended = $objMdl->toArray();
            $entroToSended = true;
        }

        $recibido = $message->toArray();
        $fSys->setPathBase($paths['chat']);
        $fSys->dumpIn($recibido);
        if($entroToSended) {
            $fSys->dumpIn($sended);
        }

        if($campo == 'precio') {
            $message->message = [
                'idItem' => $this->cotProgress['idItem'],
                'body' => $message->message
            ];
            $ftObj = new TrackFileCot($message, $paths);
            // $ftObj->finDeCotizacion();
        }
        $wh->sendMy('wa-wh', 'notSave', [
            'recibido' => $recibido,
            'enviado'  => (count($sended) == 0) ? ['body' => 'none'] : $sended,
            'trackfile'=> $this->cotProgress
        ]);
    }

    /** */
    private function isValid(String $campo, String $data): bool
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
        $this->result = $data;
        $str = mb_strtolower($this->result);
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
                    $this->result = $entera.'.'.$decimal;
                    return true;
                }
            }
        }

        $entera = $this->isDigit($str);
        if($entera != '-1') {
            $this->result = $entera.'.00';
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

}
