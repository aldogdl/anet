<?php

namespace App\Service\WapiRequest;

class ExtractMessage {

    private array $message;

    public String $pathToAnalizar = '';
    public bool $isStt = false;

    /** 
     * Extraemos la esencia real del mensaje recibido por Whatsapp.
    */
    public function __construct(array $message)
    {
        if(!$this->isSingle($message)) {
            $this->pathToAnalizar = round(microtime(true) * 1000).'.json';
        }
    }

    /** */
    public function get(): array { return $this->message; }

    /** 
     * Analizamos si el contenido del mensaje esta comprendido de listas unicas,
     * es decir, que venga solo un nivel de anidamiento en todos sus campos
     */
    private function isSingle(array $message) : bool
    {
        $phoneNumberId = '';
        $result = [];
        if(array_key_exists('entry', $message)) {
            if(count($message['entry']) == 1) {

                $result = $message['entry'][0];
                if(array_key_exists('changes', $result)) {

                    if(count($result['changes']) == 1) {

                        $result = $result['changes'][0]['value'];
                        if(array_key_exists('metadata', $result)) {
                            $phoneNumberId = $result['metadata']['phone_number_id'];
                        }

                        if(array_key_exists('messages', $result)) {
                            if(count($result['messages']) == 1) {
                                $result = $result['messages'][0];
                                $result['phone_number_id'] = $phoneNumberId;
                                $result['myTime'] = strtotime('now');
                                $this->message = $result;
                                $result = [];
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

}
