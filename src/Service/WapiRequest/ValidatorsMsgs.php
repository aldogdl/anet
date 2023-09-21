<?php

namespace App\Service\WapiRequest;

class ValidatorsMsgs {

    public $result;
    public $valids = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    /**
     * Se usa para saber si es valida la imagen valida, y si el argumento $fileCot
     * no es vacio, entonces lo hidratamos con el resultado.
     */
    public function isValidImage(array $message, array $fileCot): String {

        $this->result = $fileCot;
        if(array_key_exists('type', $message)) {

            if(array_key_exists('mime_type', $message[ $message['type'] ])) {

                $tipo = $message[ $message['type'] ]['mime_type'];
                if(in_array($tipo, $this->valids)) {
                    if(array_key_exists('fotos', $this->result['values'])) {
                        $this->result['values']['fotos'][] = $message[ $message['type'] ];
                    }else{
                        $this->result['values'] = ['fotos' => $message[ $message['type'] ]];
                    }
                    return '';
                }
                return 'invalid';
            }
        }

        return 'notFotosReply';
    }

    /**
     * Se usa para saber si el mensaje no es un audio o documento, por el
     * momento el sistema solo acepta imagenes, interactivos y texto
     */
    public function isValidFormat(array $message): String {

        if(array_key_exists('mime_type', $message[ $message['type'] ])) {

            $tipo = $message[ $message['type'] ]['mime_type'];
            if(!in_array($tipo, $this->valids)) {
                return 'invalid';
            }
        }
        return '';        
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