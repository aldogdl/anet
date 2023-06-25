<?php

namespace App\Service\WA;

use App\Service\WA\Dom\WaMessageDto;

class WaTypeResponse {

    public bool $saveMsgResult;
    
    private WaService $waS;
    private $msgFix = 'En 5 segundos recibirás otra *Oportunidad de VENTA*💰, ¡No la dejes pasar!.'; 
    public WaMessageDto $metaMsg;
    private array $message;
    private String $pathToken;
    private String $pathToWa;
    private String $fileToCot;
    private String $token;
    private array  $msgResp = [
        'cotizar'  => '😃👍 Gracias!!.. Envia *FOTOGRAFÍAS* por favor.',
        'detalles' => '👌🏼 Ok!!. Ahora los *DETALLES* de la Pieza.',
        'costo'    => '🤝🏻 Muy bien!! Tú mejor *COSTO* por favor. 😃',
        'graxCot'  => '😃👍 Mil Gracias!! Éxito en tu venta. ',
        'noTengo'  => '😃👍 Enterados!!. ',
        'errCosto' => '⚠️ Envía SÓLO NÚMERO para el *costo* por favor. ',
    ];

    /** */
    public function __construct(WaMessageDto $waEx, WaService $ws, array $msg, String $pTo, String $pToken)
    {
        $this->token      = '';
        $this->metaMsg    = $waEx;
        $this->pathToken  = $pToken;
        $this->pathToWa   = $pTo;
        $this->message    = $msg;
        $this->waS        = $ws;
        $this->fileToCot  = $this->pathToWa.'/_cotizar-'.$this->metaMsg->waId.'.json';
        $this->saveMsgResult = false;

        $this->execute();    
    }
    
    /** */
    private function execute()
    {
        if($this->metaMsg->type == 'reply') {

            if(mb_strpos($this->metaMsg->body, '_notengo' ) !== false) {

                $result = $this->sendMsg($this->msgResp['noTengo'].$this->msgFix);
                if(count($result) > 0) {
                    $this->setErrorInFile($result);
                }
                $this->saveMsgResult = true;
                return;
            }

            if(mb_strpos($this->metaMsg->body, '_cotizar' ) !== false) {
    
                $result = $this->sendMsg($this->msgResp['cotizar']);
                if(count($result) > 0) {
                    $this->setErrorInFile($result);
                }else{
                    $this->buildStepsCots();
                    $this->saveMsgResult = true;
                }
                return;
            }
        }

        if(mb_strpos(mb_strtolower($this->metaMsg->body), 'cmd:c' ) !== false) {
    
            $result = $this->sendMsg($this->msgResp['cotizar']);
            if(count($result) > 0) {
                $this->setErrorInFile($result);
            }else{
                $this->buildStepsCots();
                $this->saveMsgResult = true;
            }
            return;
        }

        // Si el mensaje no es por medio de un boton, revisamos su entrada
        $isWelcome = $this->checkinMessage();
        if(!$isWelcome) {
            return;
        }

        if($this->metaMsg->type == 'image') {
            
            if(is_file('file_image_'.$this->metaMsg->waId)) {

                unlink('file_image_'.$this->metaMsg->waId);
                $result = $this->sendMsg($this->msgResp['detalles']);
                if(count($result) > 0) {
                    $this->setErrorInFile($result);
                }else{
                    $this->setCampoCotAs('fotos', 'ok');
                }
            }
            return;
        }

        if($this->metaMsg->type == 'text') {
            
            if($this->metaMsg->campoResponsed == 'detalles') {
                
                $result = $this->sendMsg($this->msgResp['costo']);
                if(count($result) > 0) {
                    $this->setErrorInFile($result);
                }else{
                    $this->setCampoCotAs('detalles', 'ok');
                }
                return;
            }

            if($this->metaMsg->campoResponsed == 'costo') {

                if(!$this->isValidCosto()) {
                    $result = $this->sendMsg($this->msgResp['errCosto']);
                    if(count($result) > 0) {
                        $this->setErrorInFile($result);
                    }
                    return;
                }

                $result = $this->sendMsg($this->msgResp['graxCot'].$this->msgFix);
                if(count($result) > 0) {
                    $this->setErrorInFile($result);
                }else{
                    $this->setCampoCotAs('graxCot', 'ok');
                }
            }
        }
    }

    /**
     * Solo es necesario revisar el costo para saber si estan enviando un numero
     * o letras para indicar este valor.
     */
    private function isValidCosto() : bool
    {
        $str = $this->metaMsg->body;
        $str = str_replace('$', '', $str);
        $str = str_replace(',', '', $str);

        if(mb_strpos($str, '.') !== false) {

            $partes = explode('.', $str);
            $entera = $this->isDigit($partes[0]);
            if($entera != '-1') {
                $decimal = $this->isDigit($partes[1]);
                if($decimal != '-1') {
                    $this->metaMsg->body = $entera.'.'.$decimal;
                    return true;
                }
            }
        }

        $entera = $this->isDigit($str);
        if($entera != '-1') {
            $this->metaMsg->body = $entera.'.00';
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
        if(is_int($value) || ctype_digit($value)) {
            return $value;
        }
        return '-1';
    }

    /**
     * Cualquier mensaje deberá tener un archivo creado previamente que
     * indica que está respondiendo a una cotización retornando true,
     * de lo contrario retornamos false
     * 
     * Por otro lado, marcamos la variable checkin con el campo que se
     * espera esta respondiendo.
     * @return false | true
    */
    private function checkinMessage() : bool
    {
        $content = $this->getContentFileCot();
        if(count($content) == 0) {
            return false;
        }

        foreach ($content as $paso => $value) {
            if($value == 'wait') {
                $this->metaMsg->campoResponsed = $paso;
                return true;
            }
        }
        return false;
    }

    /**
     * Construimos el archivo de pasos de cotización que sirve como referencia
     * para saber en que campo estamos escribiendo actualmente
     */
    private function buildStepsCots(): void
    {
        $newCotsSteps = [
            'fotos'    => 'wait',
            'detalles' => 'wait',
            'costo'    => 'wait',
            'grax'     => 'wait',
        ];
        if(!is_file($this->fileToCot)) {
            file_put_contents($this->fileToCot, json_encode($newCotsSteps));
            file_put_contents('file_image_'.$this->metaMsg->waId, '');
        }
    }

    /**
     * Guardamos en el archivo de pasos de cotizacion el campo y su valor
     * indicados por parametro, esto se hace para saber en que campo estamos
     * actualmente queriendo escribir.
     */
    private function setCampoCotAs(String $campo, String $value) : bool
    {
        $this->saveMsgResult = true;
        $content = $this->getContentFileCot();
        if(count($content) == 0) {
            return false;
        }
        
        if($campo == 'graxCot') {
            unlink($this->fileToCot);
        }else{
            $content[$campo] = $value;
            file_put_contents($this->fileToCot, json_encode($content));
        }
        return true;
    }

    /**
     * Recuperamos el archivo que contienen los pasos de la cotizacion en curso
     */
    private function getContentFileCot() : array
    {
        if(is_file($this->fileToCot)) {
            $content = file_get_contents($this->fileToCot);
            try {
                $content = json_decode($content, true);
            } catch (\Throwable $th) {
                $this->setErrorInFile([
                    'error'  => $th->getMessage(),
                    'message'=> $content
                ]);
                return [];
            }
        }

        return $content;
    }

    /** 
     * Si hay un error guardamos un archivo con el registro complete de éste.
    */
    private function setErrorInFile(array $result) : void {

        $filename = round(microtime(true) * 1000);
        file_put_contents(
            $this->pathToWa.'/fails_'.$filename.'.json',
            json_encode([
                'razon'  => 'Mensaje no se pudo enviar a WhatsApp',
                'body'   => $result
            ])
        );
    }

    /** 
     * Enviamos un mensaje de texto a Whatsapp
    */
    private function sendMsg(String $msg) : array {

        if($this->token == '') {
            $this->token  = file_get_contents($this->pathToken);
            $this->waS->hidratarAcount($this->message, $this->token);
        }

        return $this->waS->msgText('+'.$this->metaMsg->waId, $msg, $this->metaMsg->id);
    }

}
