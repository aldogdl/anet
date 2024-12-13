<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;

/** 
 * Extraemos la esencia real del mensaje recibido por Whatsapp.
*/
class ParseMsg {

    private bool $isTest;
    private array $waMsg;
    private String $recibido;
    private array $tokenLogin = [
        'hola', 'autoparnet', 'autoparnet,', 'atenderte.', 'piezas', 'necesitas', 'necesitas?'
    ];

    /** */
    public function __construct(array $message)
    {
        $this->waMsg = $message;
        $date = round(microtime(true) * 1000);
        $this->recibido = $date."";
    }

    /** 
     * Analizamos si el contenido del mensaje esta comprendido de listas unicas,
     * es decir, que venga solo un nivel de anidamiento en todos sus campos
     */
    public function parse(bool $isTest) : WaMsgDto | null
    {
        $this->isTest = $isTest;
        if(array_key_exists('entry', $this->waMsg)) {

            if(count($this->waMsg['entry']) > 1) {
                return null;
            }

            $this->waMsg = $this->waMsg['entry'][0];
            if(array_key_exists('changes', $this->waMsg)) {

                if(count($this->waMsg['changes']) > 1) {
                    return null;
                }

                $this->waMsg = $this->waMsg['changes'][0]['value'];
                if(array_key_exists('statuses', $this->waMsg)) {

                    $this->waMsg = $this->waMsg['statuses'][0];
                    return $this->extractMsgTypeStatus();
                }

                if(array_key_exists('messages', $this->waMsg)) {
                    if(count($this->waMsg['messages']) > 1) {
                        return null;
                    }
                    $this->waMsg = $this->waMsg['messages'][0];
                    return $this->extractMessageType();
                }
            }
        }

        return null;
    }

    /**
     * Cuando no es un status el mensaje lo analizamos aqui
    */
    function extractMessageType(): WaMsgDto
    {
        if(array_key_exists('type', $this->waMsg)) {

            switch ($this->waMsg['type']) {
                case 'interactive':
                    return $this->extractInteractive();
                    break;
                case 'text':
                    return $this->extractText();
                    break;
                case 'image':
                    return $this->extractImage();
                    break;
                default:
                    return new WaMsgDto(
                        $this->isTest,
                        $this->waMsg['from'],
                        $this->waMsg['id'],
                        '',
                        '',
                        $this->waMsg['timestamp'],
                        $this->recibido,
                        TypesWaMsgs::DOC,
                        'Un '.$this->waMsg['type'].' fué enviado por el cliente',
                        "delivered",
                        "errorDeTipos"
                    );
                break;
            }
        }
    }

    /** */
    function extractText(): WaMsgDto
    {
        $txt = 'Error, no se recibió ningún texto';
        $idContext = '';
        if(array_key_exists('context', $this->waMsg)) {
            if(array_key_exists('id', $this->waMsg['context'])) {
                $idContext = $this->waMsg['context']['id'];
            }
        }

        $tipo = TypesWaMsgs::TEXT;
        $subEvent = '';
        if(array_key_exists('body', $this->waMsg[$this->waMsg['type']])) {                                        
            
            $txt = $this->waMsg[$this->waMsg['type']]['body'];
            $txt = mb_strtolower($txt);

            if(mb_strpos($txt, '/anet_') !== false) {
                $txt = str_replace('/anet_', 'anet ', $txt);
            }
            
            if(mb_strpos($txt, 'anet') !== false) {

                $tipo = TypesWaMsgs::COMMAND;
                $partes = explode('anet', $txt);
                if(count($partes) > 1) {
                    $subEvent = trim($partes[0]);
                    $txt = trim($partes[1]);
                }
            }else{
                if($this->isLoginMsg($txt)) {
                    $tipo = TypesWaMsgs::LOGIN;
                    $subEvent = 'iniLogin';
                }
            }
        }

        return new WaMsgDto(
            $this->isTest,
            $this->waMsg['from'],
            $this->waMsg['id'],
            "",
            $idContext,
            $this->waMsg['timestamp'],
            $this->recibido,
            $tipo,
            $txt,
            "delivered",
            $subEvent
        );
    }

    /** */
    function extractImage(): WaMsgDto
    {
        $idContext = '';
        if(array_key_exists('context', $this->waMsg)) {
            if(array_key_exists('id', $this->waMsg['context'])) {
                $idContext = $this->waMsg['context']['id'];
            }
        }
        $mime = '';
        if(array_key_exists('mime_type', $this->waMsg[$this->waMsg['type']])) {                                        
            $partes = explode('/', $this->waMsg[$this->waMsg['type']]['mime_type']);
            $mime = $partes[1];
        }

        return new WaMsgDto(
            $this->isTest,
            $this->waMsg['from'],
            $this->waMsg['id'],
            "",
            $idContext,
            $this->waMsg['timestamp'],
            $this->recibido,
            TypesWaMsgs::IMAGE,
            $this->waMsg[$this->waMsg['type']],
            $mime,
            'sfto'
        );
    }

    /** */
    function extractInteractive(): WaMsgDto
    {
        $btnAction = 'Error, no se recibió ningún Interactivo';
        $idContext = '';
        $idAnet = '';
        if(array_key_exists('context', $this->waMsg)) {
            $idContext = $this->waMsg['context']['id'];
        }

        $tipo = TypesWaMsgs::INTERACTIVE;
        // Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
        // que se necesita enviar como respuesta inmendiata a este
        $subEvent = '';
        if(array_key_exists('button_reply', $this->waMsg[$this->waMsg['type']])) {                                        
            $btnAction = $this->waMsg[$this->waMsg['type']]['button_reply'];
            $partes = explode('_', $btnAction['id']);
            $subEvent = $partes[0];
            $idAnet = $partes[1];
        }

        switch ($subEvent) {
            case 'cnow':
                $tipo = TypesWaMsgs::BTNCOTNOW;
                break;
            case 'ntg':
                $tipo = TypesWaMsgs::NTG;
                break;
            case 'ntga':
                $tipo = TypesWaMsgs::NTGA;
                break;
            default:
                $tipo = TypesWaMsgs::INTERACTIVE;
                break;
        }

        return new WaMsgDto(
            $this->isTest,
            $this->waMsg['from'],
            $this->waMsg['id'],
            $idAnet,
            $idContext,
            $this->waMsg['timestamp'],
            $this->recibido,
            $tipo,
            $btnAction,
            "delivered",
            $subEvent
        );
    }

    /** */
    function extractMsgTypeStatus(): WaMsgDto
    {
        $cat = 'Sin Especificar';

        if(array_key_exists('pricing', $this->waMsg)) {
            $cat = $this->waMsg['pricing']['category'];
        }
        
        $conv = [];
        if(array_key_exists('conversation', $this->waMsg)) {
            if(array_key_exists('expiration_timestamp', $this->waMsg['conversation'])) {
                $conv = [
                    'conv' => $this->waMsg['conversation']['id'],
                    'expi' => $this->waMsg['conversation']['expiration_timestamp'],
                    'type' => $this->waMsg['conversation']['origin']['type'],
                    'stt'  => $this->waMsg['status']
                ];
            }
        }

        $isExp = (count($conv) > 0) ? true : false;
        if($cat == 'Sin Especificar') {

            if(array_key_exists('errors', $this->waMsg)) {
                $rota = count($this->waMsg['errors']);
                $isExp = true;
                for ($i=0; $i < $rota; $i++) {
                    if(array_key_exists('error_data', $this->waMsg['errors'][$i])) {
                        if(mb_strpos($this->waMsg['errors'][$i]['error_data']['details'], '24 hours')) {
                            $conv = [
                                'conv' => $this->waMsg['errors'][$i]['code'],
                                'expi' => 'Mensaje de reintegración',
                                'type' => 'Sesion Caducada del Destinatario.',
                                'stt'  => $this->waMsg['status']
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return new WaMsgDto(
            $this->isTest,
            $this->waMsg['recipient_id'],
            $this->waMsg['id'],
            "",
            "",
            $this->waMsg['timestamp'],
            $this->recibido,
            TypesWaMsgs::STT,
            ($isExp) ? $conv  : ["stt" => $this->waMsg['status']],
            $cat,
            'stt'
        );
    }

    /** */
    public function isLoginMsg(String $txtMsg): bool
    {
        $palClas = [];
        if(mb_strpos($txtMsg, '_') !== false) {
            $partes = explode('_', $txtMsg);
        }else{
            $partes = explode(' ', $txtMsg);
        }

        $rota = count($partes);
        for ($i=0; $i < $rota; $i++) {
            
            $search = trim($partes[$i]);
            if(in_array($search, $this->tokenLogin)) {
                $palClas[] = $search;
            }
        }

        $res = (count($palClas) * 100) / count($this->tokenLogin);
        return ($res > 70) ? true : false;
    }

}
