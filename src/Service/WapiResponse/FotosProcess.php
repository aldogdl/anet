<?php

namespace App\Service\WapiResponse;

class FotosProcess
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
            "body" => "😃👍 Gracias!!..\n Envia *FOTOGRAFÍAS* por favor."
        ];
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
    public function isValid(array $message): bool {

        return true;
    }

    ///
    public function buildResponse(array $message, array $response): array {

        $message['response']  = [
            'type' => $response['type'],
            'body' => $response['body']
        ];
        $message['subEvento'] = 'cot';
        $message['step'] = 'fotos';
        $message['fileToCot'] = $this->pathToCot;
        return $message;
    }
}