<?php

namespace App\Service\WapiProcess;

use App\Entity\WaMsgMdl;

class ExtractMessage {

    private WaMsgMdl $message;
    private String $recibido;

    public String $pathToAnalizar = '';
    public bool $isStt = false;
    public bool $isLogin = false;
    public bool $isImage = false;
    public bool $isText = false;
    public bool $isInteractive = false;
    public bool $isCmd = false;

    public String $from = '';
    public String $phoneNumberId = '';

    private array $tokenLogin = [
        'Hola', 'AutoparNet,', 'atenderte.', 'piezas', 'necesitas?'
    ];

    /** 
     * Extraemos la esencia real del mensaje recibido por Whatsapp.
    */
    public function __construct(array $message)
    {
        $date = new \DateTime('now');
        $this->recibido = $date->format('d-m-Y');

        if(!$this->isSingle($message)) {
            $this->pathToAnalizar = round(microtime(true) * 1000).'.json';
        }
    }

    /** */
    public function get(): WaMsgMdl { return $this->message; }

    /** 
     * Analizamos si el contenido del mensaje esta comprendido de listas unicas,
     * es decir, que venga solo un nivel de anidamiento en todos sus campos
     */
    private function isSingle(array $message) : bool
    {
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
                    $this->phoneNumberId = $result['metadata']['phone_number_id'];
                }

                if(array_key_exists('statuses', $result)) {
                    $this->extractMsgTypeStatus($result['statuses'][0]);
                    return true;
                }

                if(array_key_exists('messages', $result)) {
                    if(count($result['messages']) > 1) {
                        return false;
                    }
                    $this->extractMessageType($result['messages'][0]);
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
                    case 'interactive':
                    $this->extractInteractive($msg);
                    break;
                case 'image':
                    $this->extractImage($msg);
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
        $idContext = '';
        if(array_key_exists('context', $msg)) {
            $idContext = $msg['context']['id'];
        }

        if(array_key_exists('body', $msg[$msg['type']])) {                                        
            $txt = $msg[$msg['type']]['body'];
            if(mb_strpos($txt, '[cmd]') !== false) {
                $this->isCmd = true;
            }
        }

        if(mb_strpos($txt, $this->tokenLogin[0]) !== false) {
            $this->isLoginMsg($txt);
        }
        
        $this->isText = true;
        $this->message = new WaMsgMdl(
            $msg['from'],
            $msg['id'],
            $idContext,
            $msg['timestamp'],
            $this->recibido,
            $msg['type'],
            $txt,
            "delivered"
        );
    }

    ///
    function extractImage(array $msg): void
    {
        $txt = 'Error, no se recibio ninguna Imágen';
        $idContext = '';
        if(array_key_exists('context', $msg)) {
            $idContext = $msg['context']['id'];
        }
        $mime = '';
        if(array_key_exists('mime_type', $msg[$msg['type']])) {                                        
            $partes = explode('/', $msg[$msg['type']]['mime_type']);
            $mime = $partes[1];
        }

        $this->isImage = true;
        $this->message = new WaMsgMdl(
            $msg['from'],
            $msg['id'],
            $idContext,
            $msg['timestamp'],
            $this->recibido,
            $msg['type'],
            $msg[$msg['type']],
            $mime,
            'sfto'
        );
    }

    ///
    function extractInteractive(array $msg): void
    {
        $btnAction = 'Error, no se recibio ningun Interactivo';
        $idContext = '';
        if(array_key_exists('context', $msg)) {
            $idContext = $msg['context']['id'];
        }

        // Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
        // que se necesita enviar como respuesta inmendiata a este
        $subEvent = '';
        if(array_key_exists('button_reply', $msg[$msg['type']])) {                                        
            $btnAction = $msg[$msg['type']]['button_reply'];
            $action = $btnAction['id'];
            $partes = explode('_', $action);
            $subEvent = $partes[0];
            $btnAction['idItem'] = $partes[1];
        }

        $this->isInteractive = true;
        $this->message = new WaMsgMdl(
            $msg['from'],
            $msg['id'],
            $idContext,
            $msg['timestamp'],
            $this->recibido,
            $msg['type'],
            $btnAction,
            "delivered",
            $subEvent
        );
    }

    /** */
    function extractMsgTypeStatus(array $result): void
    {
        $this->isStt = true;
        $this->from = $result['recipient_id'];
        $cat = 'Sin Especificar';

        if(array_key_exists('pricing', $result)) {
            $cat = $result['pricing']['category'];
        }
        
        $conv = [];
        if(array_key_exists('conversation', $result)) {
            if(array_key_exists('expiration_timestamp', $result['conversation'])) {
                $conv = [
                    'conv' => $result['conversation']['id'],
                    'expi' => $result['conversation']['expiration_timestamp'],
                    'type' => $result['conversation']['origin']['type']   ,
                    'stt'  => $result['status']   
                ];
            }
        }

        $isExp = (count($conv) > 0) ? true : false;
        if($cat == 'Sin Especificar') {
            if(array_key_exists('errors', $result)) {
                $rota = count($result['errors']);
                for ($i=0; $i < $rota; $i++) {
                    if(array_key_exists('error_data', $result['errors'][$i])) {
                        if(mb_strpos($result['errors'][$i]['error_data']['details'], '24 hours')) {
                            $conv = [
                                'code' => $result['errors'][$i]['code'],
                                'title'=> 'Mensaje de reintegración',
                                'body' => 'Sesion Caducada del Destinatario.',
                                'stt'  => $result['status']
                            ];
                            break;
                        }
                    }
                }
            }
        }
        
        $this->message = new WaMsgMdl(
            $this->from,
            $result['id'],
            "",
            $result['timestamp'],
            $this->recibido,
            ($isExp) ? "expi" : "text",
            ($isExp) ? $conv  : $result['status'],
            $cat,
            'stt'
        );
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
