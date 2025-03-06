<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Enums\TypesWaMsgs;

/** 
 * Extraemos la esencia real del mensaje recibido por Whatsapp.
*/
class ParseMsg {

    private String $code = '#';
    private bool $isTest;
    private array $waMsg;
    private String $recibido;
    public bool $isQC;

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
        $this->isQC = false;

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
    function extractMessageType(): ?WaMsgDto
    {
        if(!array_key_exists('type', $this->waMsg)) {
            return null;
        }

        // Detectar si es un QuienCon
        $this->isQCMsg();

        switch ($this->waMsg['type']) {
            case 'button':
                return $this->extractDataFromButton();
                break;
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

    /** */
    private function isQCMsg(): void
    {
        $txt = '';
        if(array_key_exists('body', $this->waMsg[$this->waMsg['type']])) {
            $txt = $this->waMsg[$this->waMsg['type']]['body'];
        }

        if(array_key_exists('caption', $this->waMsg[$this->waMsg['type']])) {
            $txt = $this->waMsg[$this->waMsg['type']]['caption'];
        }

        $txt = mb_strtolower($txt);
        if(mb_strpos($txt, $this->code) !== false) {
            
            $partes = explode(' ', $txt);
            $rota = count($partes);
            if($rota > 0) {
                if($partes[0] == $this->code) {
                    if($partes[0].$partes[1] == $this->code.'qc') {
                        $this->isQC = true;
                    }
                }else if($partes[0] == $this->code.'qc') {
                    $this->isQC = true;
                }
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
            $txt = mb_strtolower(trim($txt));
            
            // Estos son comandos realizados desde Whatsapp
            if(mb_strpos($txt, 'login') !== false) {
                $txt = $this->code.'ok';
            }

            if(mb_strpos($txt, $this->code) !== false) {
                
                $tipo = TypesWaMsgs::COMMAND;
                $txt = str_replace($this->code, '', $txt);
                $txt = mb_strtolower(trim($txt));
                
                if($txt == 'login' || $txt == 'ok') {
                    $tipo = TypesWaMsgs::LOGIN;
                    $subEvent = 'iniLogin';
                } elseif($txt == 'demo') {
                    $subEvent = $txt;
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
    function extractDataFromButton(): WaMsgDto
    {
        $btnAction = 'Error, no se recibió ningún Botón';
        $idContext = '';
        if(array_key_exists('context', $this->waMsg)) {
            $idContext = $this->waMsg['context']['id'];
        }

        $tipo = TypesWaMsgs::BUTTON;
        // Todo mensaje interactivo debe incluir en su ID como primer elemento el mensaje
        // que se necesita enviar como respuesta inmendiata a este
        $subEvent = '';
        if(array_key_exists('payload', $this->waMsg[$this->waMsg['type']])) {  
                                                  
            $btnAction = $this->waMsg[$this->waMsg['type']]['payload'];
            if(mb_strpos($btnAction, 'QUIERO Recibir') !== false) {
                $tipo = TypesWaMsgs::LOGIN;
                $subEvent = 'iniLogin';
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
            $btnAction,
            "delivered",
            $subEvent
        );
    }

    /** */
    function extractInteractive(): WaMsgDto
    {
        $btnAction = 'Error, no se recibió ningún Interactivo';
        $idContext = '';
        $idDbSr = '';
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
            if(mb_strpos($partes[1], 'demo') !== false) {
                $partes = explode('-', $partes[1]);
                $btnAction = 'demo';
                $partes[1] = (integer) $partes[1];
            }
            $idDbSr = $partes[1];
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
            case 'cotNowFrm':
                // Cotizar ahora canal via Formulario App
                $tipo = TypesWaMsgs::COTNOWFRM;
                break;
            case 'cotNowWa':
                // Cotizar ahora canal via Whatsapp puro
                $tipo = TypesWaMsgs::COTNOWWA;
                break;
            default:
                $tipo = TypesWaMsgs::INTERACTIVE;
                break;
        }
        
        return new WaMsgDto(
            $this->isTest,
            $this->waMsg['from'],
            $this->waMsg['id'],
            $idDbSr,
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

}
