<?php

namespace App\Service\WapiRequest;

class ExtractMessage {

    private array $message;

    public String $pathToAnalizar = '';
    public bool $isStt = false;
    public String $from = '';

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

                        if(array_key_exists('statuses', $result)) {
                            
                            $this->isStt = true;
                            $result = $result['statuses'][0];
                            $cat = 'Sin Especificar';
                            if(array_key_exists('pricing', $result)) {
                                $cat = $result['pricing']['category'];
                            }

                            $status = [
                                'id' => $result['id'],
                                'status' => $result['status'],
                                'timestamp' => $result['timestamp'],
                                'from' => $result['recipient_id'],
                                'category' => $cat,
                                'phone_number_id' => $phoneNumberId,
                                'myTime' => ''.strtotime('now'),
                                'subEvento' => 'stt',
                            ];
                            
                            $this->from = $result['recipient_id'];
                            if(array_key_exists('conversation', $result)) {
                                if(array_key_exists('expiration_timestamp', $result['conversation'])) {
                                    $status['expiration_timestamp'] = $result['conversation']['expiration_timestamp'];
                                }
                            }
                            
                            $this->message = $status;
                            $status = [];
                            return true;
                        }

                        if(array_key_exists('messages', $result)) {
                            if(count($result['messages']) == 1) {
                                $result = $result['messages'][0];
                                $this->from = $result['from'];
                                $result['phone_number_id'] = $phoneNumberId;
                                $result['myTime'] = ''.strtotime('now');
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
