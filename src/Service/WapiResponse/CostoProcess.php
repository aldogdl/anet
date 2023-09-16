<?php

namespace App\Service\WapiResponse;

class CostoProcess
{
    public String $pathToCot = '';

    /** */
    public function __construct(String $pathToCot)
    {
        $this->pathToCot = $pathToCot;
    }

    ///
    public function getMessage(array $inTransit): array {

        return [
            "context" => $inTransit["wamid"],
            "type" => "button",
            "body" => [
                "text" => "👌🏼 Ok!!, Ahora escribe:\n Los *DETALLES* de la Pieza o...\nUtiliza unos de los botones frecuentes."
            ],
            "footer" => [
                "text" => '📷 _Puedes enviar *más fotos* si lo deseas._'
            ],
            "action" => [
                "buttons" => [
                    [
                        "type" => "reply",
                        "reply" => [
                            "id" => "asNew",
                            "title" => "ESTA COMO NUEVA"
                        ]
                    ],
                    [
                        "type" => "reply",
                        "reply" => [
                            "id" => "normal",
                            "title" => "ESTADO NORMAL DE USO"
                        ]
                    ]
                ]
            ]
        ];
        
    }

    ///
    public function isValid(array $message, array $fileCot): String {

        if(array_key_exists('type', $message)) {
            if(array_key_exists('mime_type', $message[ $message['type'] ])) {
                $fileCot['values'][ $fileCot['current'] ][] = $message[ $message['type'] ];
                return true;
            }
        }

        return 'notDeta';
    }
    
    ///
    public function getMessageError(String $tipo, array $inTransit): array {

        $msgs = [
            'replyBtn' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "📝 Se esperaban Detalles de la Pieza\n\n🚗 Cotización en Curso..."
            ],
            'notDeta' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "⚠️ Los detalles no son validos, se más específico por favor."
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