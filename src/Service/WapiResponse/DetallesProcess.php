<?php

namespace App\Service\WapiResponse;

use App\Service\WapiRequest\ValidatorsMsgs;

class DetallesProcess
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
                "text" => "👌🏼 Ok!!, Ahora escribe:\n\nLos *DETALLES* de la Pieza o...\n\nUtiliza unos de los botones frecuentes. 👇🏻"
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
                            "title" => "ESTá COMO NUEVA"
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
                $fileCot['values']['fotos'][] = $message[ $message['type'] ];
                return 'image';
            }

            if(array_key_exists('body', $message[ $message['type'] ])) {
                
                $deta = $message[ $message['type'] ]['body'];
                if(strlen($deta) < 3) {
                    return 'notDeta';
                }

                $isNum = new ValidatorsMsgs();
                if($isNum->isValidNumero($deta)) {
                    return 'invalid';
                }

                $fileCot['values'][ $fileCot['current'] ][] = $message[ $message['type'] ];
                return '';
            }
        }

        return 'unknow';
    }

    ///
    public function getMessageError(String $tipo, array $inTransit): array {

        $msgs = [
            'replyBtn' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "📝 Se esperaban los *Detalles* de la Pieza.\n\n🚗 Cotización en Curso..."
            ],
            'notDeta' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "⚠️ Los Detalles no son válidos, se más específico por favor."
            ],
            'invalid' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "⚠️ Escribe una combinación de letras y números para los Detalles, por favor."
            ],
            'unknow' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "😱 Error desconocido, enviar el valor nuevamente por favor."
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