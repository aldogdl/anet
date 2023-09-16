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
                "body" => "📷 Se esperaban *Fotografías*.\n\n🚗 Cotización en Curso..."
            ],
            'notFotos' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "📷 Se esperaban Fotografías\n\n🚗 Cotización en Curso..."
            ],
            'notFotosReply' => [
                "context" => $inTransit["wamid"],
                "type" => "button",
                "body" => [
                    "text" => "⚠️ Las Fotos son Importantes pero..."
                ],
                "footer" => [
                    "text" => 'Si deseas continuar sin fotos, presiona el siguiente botón'
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "conti_sin_fotos",
                                "title" => "CONTINUAR SIN FOTOS"
                            ]
                        ]
                    ]
                ]
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
        $message['step'] = 'fotos';
        $message['fileToCot'] = $this->pathToCot;
        return $message;
    }
}