<?php

namespace App\Service\WapiResponse;

use App\Service\WapiRequest\ValidatorsMsgs;

class DetallesProcess
{
    public array $fileCot;
    public String $pathToCot = '';

    /** */
    public function __construct(String $pathToCot)
    {
        $this->pathToCot = $pathToCot;
    }

    /**
     * Si el archivo existe enviamos el mensaje de detalles, de lo contrario es
     * que ya lo enviamos con anterioridad.
     */
    public function isMsgInique(): bool {

        if(is_file($this->pathToCot.'.det')) {
            unlink($this->pathToCot.'.det');
            return true;
        }
        return false;
    }

    /** */
    public function getMessage(String $wamid): array {

        return [
            "context" => $wamid,
            "type" => "button",
            "body" => [
                "text" => "👌🏼 Ok!!, Ahora escribe:\n\nLos *DETALLES* de la Pieza o...\n\nUtiliza unos de los botones frecuentes. 👇🏻"
            ],
            "footer" => [
                "text" => '📷 _En este Paso aún puedes enviar FOTOS si lo deseas._'
            ],
            "action" => [
                "buttons" => [
                    [
                        "type" => "reply",
                        "reply" => [
                            "id" => "good",
                            "title" => "BUENAS CONDICIONES"
                        ]
                    ],
                    [
                        "type" => "reply",
                        "reply" => [
                            "id" => "normal",
                            "title" => "DETALLES DE USO"
                        ]
                    ],
                    [
                        "type" => "reply",
                        "reply" => [
                            "id" => "reparada",
                            "title" => "AUTOPARTE REPARADA"
                        ]
                    ]
                ]
            ]
        ];
        
    }

    /**
     * Debe guardar el valor valido en el archivo de cotizacion en transito
     * y a su ves validar si no nos estan enviando mas fotos, o si es un
     * evento por medio de texto o un boton frecuente
     */
    public function isValid(array $message, array $fileCots, String $respBtn): String {

        $this->fileCot = $fileCots;

        if(array_key_exists('type', $message)) {

            $val = new ValidatorsMsgs();

            // Revisamos primero si lo que nos estan enviando son fotos
            $isImg = $val->isValidImage($message, $this->fileCot);
            $this->fileCot = $val->result;
            if($isImg == '') {
                // Es una imagen, la guardamos en el archivo DE RESPUESTAS
                file_put_contents($this->pathToCot, json_encode($this->fileCot));
                return 'image';
            }

            // Si no hay que validar es por que presionaron un boton, pero hay que
            // revisar si hay fotos.
            if($respBtn != '') {

                $this->fileCot['values'][$this->fileCot['current']] = $respBtn;
                file_put_contents($this->pathToCot, json_encode($this->fileCot));

                if(array_key_exists('fotos', $this->fileCot['values'])) {
                    if(count($this->fileCot['values']['fotos']) > 0) {
                        return '';
                    }
                }

                return 'notFotosReply';
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

                $this->fileCot['values'][$this->fileCot['current']] = $deta;
                file_put_contents($this->pathToCot, json_encode($this->fileCot));
                return '';
            }
        }

        return 'unknow';
    }

    ///
    public function getMessageError(String $tipo, String $wamid): array {

        $msgs = [
            'replyBtn' => [
                "context" => $wamid,
                "preview_url" => false,
                "body" => "📝 Se esperaban los *Detalles* de la Pieza.\n\n🚗 Cotización en Curso..."
            ],
            'notDeta' => [
                "context" => $wamid,
                "preview_url" => false,
                "body" => "⚠️ Los Detalles no son válidos, se más específico por favor."
            ],
            'invalid' => [
                "context" => $wamid,
                "preview_url" => false,
                "body" => "⚠️ Escribe una combinación de letras y números para los Detalles, por favor."
            ],
            'unknow' => [
                "context" => $wamid,
                "preview_url" => false,
                "body" => "😱 Error desconocido al leer los *DETALLES*, envialos nuevamente por favor."
            ]
        ];

        return $msgs[$tipo];
    }

    ///
    public function buildResponse(array $message, array $response): array {

        if(count($response) > 0) {
            $message['response']  = [
                'type' => $response['type'],
                'body' => $response['body']
            ];
        }
        $message['subEvento'] = 'cot';
        $message['step'] = 'detalles';
        $message['fileToCot'] = $this->pathToCot;
        return $message;
    }
}