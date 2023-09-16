<?php

namespace App\Service\WapiResponse;

class DetallesProcess
{
    public String $pathToCot = '';

    /** */
    public function __construct(String $pathToCot)
    {
        $this->pathToCot = $pathToCot;
    }

    ///
    public function getMessage(): array {

        return [
            "preview_url" => false,
            "body" => "👌🏼 Ok!!, Ahora...\n Los *DETALLES* de la Pieza.\n\n📷 _Puedes enviar *más fotos* si lo deseas._"
        ];
    }

    ///
    public function isValid(array $message, array $fileCot): bool {

        if(array_key_exists('type', $message)) {
            if(array_key_exists('mime_type', $message[ $message['type'] ])) {
                $fileCot['values'][ $fileCot['current'] ][] = $message[ $message['type'] ];
                return true;
            }
        }
        return false;
    }
    
    ///
    public function getMessageError(String $tipo, array $inTransit): array {

        $msgs = [
            'replyBtn' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "📷 Se esperaban Fotografías\n\n🚗 Cotización en Curso..."
            ],
            'notFotos' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "📷 Se esperaban Fotografías\n\n🚗 Cotización en Curso..."
            ]
        ];
        
        return $msgs[$tipo];
    }

    ///
    public function buildResponse(array $message, array $response): array {

        $message['response']  = [
            'type' => $response['type'],
            'body' => $response['body']
        ];
        $message['subEvento'] = 'cot';
        $message['step'] = 'detalles';
        $message['fileToCot'] = $this->pathToCot;
        return $message;
    }
}