<?php

namespace App\Service\WA;

use App\Service\WA\Dom\CotizandoPzaDto;
use App\Service\WA\Dom\WaMessageDto;

class WaTypeResponse {

    public bool $saveMsgResult;
    
    private $msgFix = "\nEn 5 segundos recibirás otra *Oportunidad de VENTA*💰\n¡No la dejes pasar!."; 
    private WaService $waS;
    public WaMessageDto $metaMsg;
    private array $message;
    private String $pathToken;
    private String $pathToWa;
    private String $pathToCots;
    private String $pathToSols;
    private String $fileToCot;
    private String $token;
    
    private array  $msgResp = [
        'fotos'    => "😃👍 Gracias!!..\n Envia *FOTOGRAFÍAS* por favor.",
        'detalles' => "👌🏼 Ok!!, Ahora...\n Los *DETALLES* de la Pieza.\n\n📷 _Puedes enviar *más fotos* si lo deseas._",
        'costo'    => "🤝🏻 Muy bien!!\n Tú mejor *COSTO* por favor. 😃",
        'graxCot'  => "😃👍 Mil Gracias!! Éxito en tu venta.\n",
        'noTengo'  => "😃👍 Enterados!!.\n",
        'errCosto' => "⚠️ Para el *costo*\n Envía SÓLO NÚMERO por favor. ",
        'noFinCot' => "✋🏼 No terminaste de *COTIZAR* la pieza siguiente:",
        'login'    => "✋🏼 Buen Día!! el Sistema Autoparnet, ya *Inició tu sesion de hoy*, Gracias!! 😃",
        'close_free' => "✋🏼 La conversación con el asesor se ha cerrado!!.\n Continuamos con la sesión de COTIZACIONES abierta. 😃",
    ];
    private array  $msgRespPendientes = [
        'fotos'    => "⚠️ No haz enviado.\n Las *FOTOS* de esta pieza.",
        'detalles' => "⚠️ Faltó indicar...\n Los *DETALLES* de esta Pieza.",
        'costo'    => "⚠️ Faltó escribir...\n Cual sería tú mejor *COSTO*. 😃"
    ];

    /** */
    public function __construct(
        WaMessageDto $waEx, WaService $ws, array $msg, String $pTo, String $pToken,
        String $pToSolicitudes, String $pToCots
    )
    {
        $this->token      = '';
        $this->metaMsg    = $waEx;
        $this->pathToken  = $pToken;
        $this->pathToWa   = $pTo;
        $this->pathToSols = $pToSolicitudes;
        $this->pathToCots = $pToCots;
        $this->message    = $msg;
        $this->waS        = $ws;
        $this->fileToCot  = $this->pathToWa.'/_cotizar-'.$this->metaMsg->waId.'.json';
        $this->saveMsgResult = false;

        $this->execute();    
    }

    /** */
    private function execute()
    {
        $isInitCot  = false;
        $isTest = false;
        
        if($this->metaMsg->type == 'close_free') {
            
            file_put_contents('siLlegoAClose.txt', $this->msgResp['close_free']);
            $this->metaMsg->msgResponse = $this->msgResp[$this->metaMsg->type];
            $result = $this->sendMsg($this->metaMsg->msgResponse);
            if(count($result) > 0) {
                $this->metaMsg->msgError = $result;
                $this->setErrorInFile($this->metaMsg->msgError);
            }
            $this->saveMsgResult = false;
            file_put_contents('siLlegoAClose_fin.txt', '');
            return;
        }

        if($this->metaMsg->type == 'login') {

            $this->metaMsg->msgResponse = $this->msgResp['login'];
            $result = $this->sendMsg($this->metaMsg->msgResponse);
            if(count($result) > 0) {
                $this->metaMsg->msgError = $result;
                $this->setErrorInFile($this->metaMsg->msgError);
            }
            return;
        }

        // Exclusivo para pruebas y capacitaciones
        if(mb_strpos(mb_strtolower($this->metaMsg->body), 'cmd:c' ) !== false) {
            $isInitCot  = true;
            $isTest = true;
            $this->saveMsgResult = false;
        }

        $steps = new CotizandoPzaDto($isTest, $this->metaMsg->body);
        $isCot = $this->checkIfHasCotsMaker($steps);

        if($isCot) {
            $this->saveMsgResult = false;
            return;
        }

        if($this->metaMsg->type == 'reply') {

            if( mb_strpos($this->metaMsg->body, '_notengo' ) !== false) {

                $this->metaMsg->msgResponse = $this->msgResp['noTengo'].$this->msgFix;
                $result = $this->sendMsg($this->metaMsg->msgResponse, false);
                if(count($result) > 0) {
                    $this->metaMsg->msgError = $result;
                    $this->setErrorInFile($this->metaMsg->msgError);
                }
                $this->metaMsg = $steps->setDataResponse($this->metaMsg);
                $this->saveMsgResult = true;
                $this->saveHasCotsMaker($steps);
                return;
            }

            if( mb_strpos($this->metaMsg->body, '_cotizar' ) !== false) {
                $isInitCot = true;
                $this->saveMsgResult = true;
            }
        }

        if($isInitCot) {

            // Revisamos si no hay una cotización en curso, primeramente lo que
            // hacemos es revisar si existe un archivo de cotizacion de este waId
            $has = $this->getContentFileCot();
            if(count($has) > 0) {

                $this->responseOnCotEnProgreso($has);
                $this->saveMsgResult = false;
                return;

            }else{

                $this->metaMsg->msgResponse = $this->msgResp['fotos'];
                $result = $this->sendMsg($this->metaMsg->msgResponse, false);
                
                if(count($result) > 0) {

                    $this->metaMsg->msgError = $result;
                    $this->setErrorInFile($this->metaMsg->msgError);
                }else{

                   $this->buildStepsCots($steps);
                }
            }

            return;
        }

        // Si el mensaje no es por medio de un boton, revisamos su entrada
        $cotResult = $this->checkinMessage();
        if(!$cotResult['hasCampo']) {
            // Si el contacto no cuenta con un archivo de cotizacion en curso
            // es que quiso comunicarse con nosotros. TODO(POR HACER)...
            $this->saveMsgResult = false;
            return;
        }
        
        $steps->fromArray($cotResult['coti']);
        $cotResult = [];

        if($this->metaMsg->type == 'image') {

            $this->metaMsg->campoResponsed = 'fotos';
            $sendMsg = false;
            $saveMsg = true;
            if(is_file('file_image_'.$this->metaMsg->waId)) {

                unlink('file_image_'.$this->metaMsg->waId);
                $sendMsg = true;

            }else{

                // Ya se recibieron fotos y se envio el mensaje de detalles
                // pero si vuelve el cotizador a enviar otras fotos entonces
                // le re-enviamos el mensjae de detalles, pero ya no guardamos
                // el campo ok en fotos dentro del objeto cotizador
                if($steps->fotos > 0 && $steps->detalles == 'wait') {
                    $sendMsg = true;
                    if(is_file('detalles_sended_'.$this->metaMsg->waId)) {
                        $sendMsg = false;
                        // Como saber si han pasado mas de 2 segundos despues de
                        // la ultima foto recibida.
                        $current = time();
                        $diff = ($current - $steps->fotos);
                        $segs = date('s', $diff);
                        if($segs > 4) {
                            $sendMsg = true;
                        }
                    }
                }
            }

            $this->saveMsgResult = true;

            if($sendMsg) {

                $this->metaMsg->msgResponse = $this->msgResp['detalles'];
                $result = $this->sendMsg($this->metaMsg->msgResponse);

                if(count($result) > 0) {
                    $this->metaMsg->msgError = $result;
                    $this->setErrorInFile($this->metaMsg->msgError);
                }else{
                    // Creamos esta marca como aviso para el sistema que ya se envio
                    // el mensaje del detalles.
                    file_put_contents('detalles_sended_'.$this->metaMsg->waId, '');
                    if($saveMsg) {
                        $this->setCampoCotAs('fotos', time(), $steps->toArray());
                    }
                }
            }

            return;
        }

        if($this->metaMsg->type == 'text') {
            
            if($this->metaMsg->campoResponsed == 'detalles') {
                
                $this->metaMsg->msgResponse = $this->msgResp['costo'];
                $result = $this->sendMsg($this->metaMsg->msgResponse);
                if(count($result) > 0) {
                    $this->metaMsg->msgError = $result;
                    $this->setErrorInFile($this->metaMsg->msgError);
                }else{
                    $this->saveMsgResult = true;
                    $this->setCampoCotAs('detalles', 'ok', $steps->toArray());
                    unlink('detalles_sended_'.$this->metaMsg->waId);
                }

                return;
            }

            if($this->metaMsg->campoResponsed == 'costo') {

                if(!$this->isValidCosto()) {

                    $this->metaMsg->msgResponse = $this->msgResp['errCosto'];
                    $result = $this->sendMsg($this->metaMsg->msgResponse);
                    if(count($result) > 0) {
                        $this->metaMsg->msgError = $result;
                        $this->setErrorInFile($this->metaMsg->msgError);
                    }else {
                        $this->saveMsgResult = true;
                    }

                    return;
                }

                $this->metaMsg->msgResponse = $this->msgResp['graxCot'].$this->msgFix;
                $result = $this->sendMsg($this->metaMsg->msgResponse);
                if(count($result) > 0) {
                    $this->metaMsg->msgError = $result;
                    $this->setErrorInFile($this->metaMsg->msgError);
                }else{
                    $this->saveHasCotsMaker($steps);
                    $this->setCampoCotAs('graxCot', 'ok', $steps->toArray());
                }
            }
        }
    }

    /**
     * Se encontró una cotizacion en progreso y respondemos a esta situacion
     */
    private function responseOnCotEnProgreso(array $cot) : void
    {
        $this->sendMsg($this->msgResp['noFinCot']);

        $fetchPza = new FinderPiezaSolicitud($this->pathToSols);
        $msgFetched = $fetchPza->determinarPzaAndStepCot($cot);
        if($fetchPza->isOkSend) {

            if($msgFetched != '' && $fetchPza->stepFinder != '') {
                $this->waS->msgText($this->metaMsg->phone, $msgFetched);
                $this->waS->msgText(
                    $this->metaMsg->phone, $this->msgRespPendientes[$fetchPza->stepFinder]
                );
            }
        }    
    }

    /**
     * Solo es necesario revisar el costo para saber si estan enviando un numero
     * o letras para indicar este valor.
     */
    private function isValidCosto() : bool
    {
        $str = mb_strtolower($this->metaMsg->body);
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
    private function checkinMessage() : array
    {
        $content = $this->getContentFileCot();
        if(count($content) == 0) {
            return false;
        }

        $result = [
            'coti' => $content,
            'hasCampo' => false,
        ];

        foreach ($content as $paso => $value) {
            if($value == 'wait') {
                $this->metaMsg->campoResponsed = $paso;
                $result['hasCampo'] = true;
                return $result;
            }
        }
        return $result;
    }

    /**
     * Construimos el archivo de pasos de cotización que sirve como referencia
     * para saber en que campo estamos escribiendo actualmente
     */
    private function buildStepsCots(CotizandoPzaDto $cots): void
    {
        if(!is_file($this->fileToCot)) {
            file_put_contents($this->fileToCot, json_encode($cots->toArray()));
            file_put_contents('file_image_'.$this->metaMsg->waId, '');
            $this->metaMsg = $cots->setDataResponse($this->metaMsg);
        }
    }

    /**
     * Cada ves que termina de cotizar una pieza creamos un archivo bacio, solo
     * para saber cual pieza ya fue atendida y no volverla a cotizar
    */
    private function checkIfHasCotsMaker(CotizandoPzaDto $cot): bool
    {
        $fn = $this->metaMsg->waId.'_'.$cot->idPza.'_'.$cot->idSol.'.cot';
        if(is_file($this->pathToCots.$fn)) {
            $msg = "*UPS!!* 😱 \n _Esta solicitud ya la cotizaste._\n\nSelecciona otra de tu lista o espera a nuevas oportunidades de venta.\n*Gracias por tu atención*.";
            $this->sendMsg($msg, false);
            return true;
        }
        return false;
    }

    /** 
     * Cada ves que termina de cotizar una pieza creamos un archivo bacio, solo
     * para saber cual pieza ya fue atendida y no volverla a cotizar
    */
    private function saveHasCotsMaker(CotizandoPzaDto $cot): void
    {
        if($cot->idPza != '0') {
            $fn = $this->metaMsg->waId.'_'.$cot->idPza.'_'.$cot->idSol.'.cot';
            file_put_contents($this->pathToCots.$fn, '');
        }
    }
    
    /**
     * Guardamos en el archivo de pasos de cotizacion el campo y su valor
     * indicados por parametro, esto se hace para saber en que campo estamos
     * actualmente queriendo escribir.
     */
    private function setCampoCotAs(String $campo, String | int $value, array $old = []) : bool
    {
        if(count($old) == 0) {
            $content = $this->getContentFileCot();
            if(count($content) == 0) {
                return false;
            }
        }else{
            $content = $old;
            $old = [];
        }

        if(!$content['isTest']) {
            $this->saveMsgResult = true;
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
        $content = [];
        if(is_file($this->fileToCot)) {

            $content = file_get_contents($this->fileToCot);
            try {
                $content = json_decode($content, true);
                $this->metaMsg->idSol  = $content['idSol'];
                $this->metaMsg->idPza  = $content['idPza'];
                $this->metaMsg->idHive = $content['idHive'];
                if($this->metaMsg->campoResponsed == '') {
                    $this->metaMsg->campoResponsed = $content['accion'];
                }
            } catch (\Throwable $th) {

                $this->setErrorInFile([
                    'error'  => $th->getMessage(),
                    'message'=> $content
                ]);
                $content = [];
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
    private function sendMsg(String $msg, bool $includeId = true) : array {

        if($this->token == '') {
            $this->token  = file_get_contents($this->pathToken);
            $this->waS->hidratarAcount($this->message, $this->token);
        }

        if($includeId) {
            return $this->waS->msgText('+'.$this->metaMsg->phone, $msg, $this->metaMsg->id);
        }else{
            return $this->waS->msgText('+'.$this->metaMsg->phone, $msg);
        }
    }

}
