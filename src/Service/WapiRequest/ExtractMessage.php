<?php

namespace App\Service\WapiRequest;

class ExtractMessage {

    private array $message;

    public String $pathToAnalizar = '';
    public bool $isStt = false;
    public bool $isCmd = false;
    public String $from = '';
    public bool $isLogin = false;

    private array $tokenLogin = [
        'Hola', 'AutoparNet,', 'atenderte.', 'piezas', 'necesitas?'
    ];

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
            if(count($message['entry']) > 1) {
                return false;
            }

            $result = $message['entry'][0];
            if(array_key_exists('changes', $result)) {

                if(count($result['changes']) > 1) {
                    return false;
                }

                $result = $result['changes'][0]['value'];
                if(array_key_exists('metadata', $result)) {
                    $phoneNumberId = $result['metadata']['phone_number_id'];
                }

                if(array_key_exists('statuses', $result)) {

                    $result = $this->extractMsgTypeStatus($result['statuses'][0]);
                    $result['phone_number_id'] = $phoneNumberId;
                    return true;
                }

                if(array_key_exists('messages', $result)) {
                    if(count($result['messages']) > 1) {
                        return false;
                    }
                    $this->extractMessageType($result['messages'][0]);
                    $this->message['phone_number_id'] = $phoneNumberId;
                    return true;
                }
            }
        }

        return false;
    }

    /** */
    function extractMessageType(array $msg): void
    {
        $this->from = $msg['from'];
        
        if(array_key_exists('type', $msg)) {
            switch ($msg['type']) {
                case 'text':
                    $this->extractText($msg);
                    break;
                
                case 'text':
                    $this->extractText($msg);
                    break;
                
                default:
                    # code...
                    break;
            }
        }
    }

    ///
    function extractText(array $msg): void
    {
        $txt = 'Error, no se recibio ningun texto';
        if(array_key_exists('body', $msg['text'])) {                                        
            $txt = $msg['text']['body'];
            if(mb_strpos($txt, '[cmd]') !== false) {
                $this->isCmd = true;
            }
        }

        $idContext = '';
        if(array_key_exists('context', $msg)) {
            $idContext = $msg['context']['id'];
        }

        if(mb_strpos($txt, $this->tokenLogin[0]) !== false) {
            $this->isLoginMsg($txt);
        }

        $this->message = [
            'from'     => $msg['from'],
            'id'       => $msg['id'],
            'context'  => $idContext,
            'creado'   => $msg['timestamp'],
            'recibido' => ''.strtotime('now'),
            'type'     => 'text',
            'message'  => $txt,
        ];
        $result = [];
    }

    /** */
    function extractMsgTypeStatus(array $result): array
    {
        $this->isStt = true;
        $cat = 'Sin Especificar';
        if(array_key_exists('pricing', $result)) {
            $cat = $result['pricing']['category'];
        }

        $status = [
            'id'        => $result['id'],
            'status'    => $result['status'],
            'timestamp' => $result['timestamp'],
            'from'      => $result['recipient_id'],
            'category'  => $cat,
            'myTime'    => ''.strtotime('now'),
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
        return $this->message;
    }

    /** */
    public function isLoginMsg(String $txtMsg)
    {
        $palClas = [];
        $partes = explode(' ', $txtMsg);
        $rota = count($partes);
        for ($i=0; $i < $rota; $i++) {

            $search = trim($partes[$i]);
            if(in_array($search, $this->tokenLogin)) {
                $palClas[] = $search;
            }
        }
        
        if(count($this->tokenLogin) == count($palClas)) {
            $this->isLogin = true;
        }
    }
}
