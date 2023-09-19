<?php

namespace App\Service\WapiResponse;

use App\Service\WapiRequest\ValidatorsMsgs;

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
            "body" => "😃👍 Gracias!!..\n Envía de 1 a 8 *FOTOS* por favor."
        ];
    }

    ///
    public function isValid(array $message, array $fileCot): String {

        $v = new ValidatorsMsgs();
        $valid = $v->isValidImage($message, $fileCot);
        if($valid == '') {
            file_put_contents($this->pathToCot, json_encode($v->result));
        }

        return $valid;
    }

    ///
    public function getMessageError(String $tipo, string $wamid): array {

        $msgs = [
            'invalid' => [
                "context" => $wamid,
                "preview_url" => false,
                "body" => "⚠️ Lo sentimos por el momento solo fotos de tipo JPG | PNG | WEBP"
            ],
            'notFotosReply' => [
                "context" => $wamid,
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