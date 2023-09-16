<?php

namespace App\Service\WapiResponse;

use App\Service\WapiRequest\ValidatorsMsgs;

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
            "preview_url" => false,
            "body" => "😃👌🏼 Perfecto Gracias, ahora...\n\nTu *MEJOR* COSTO de proveedor 🤝🏻.\n\n🔖 Escribe con sólo números por favor."
        ];
        
    }

    ///
    public function getMessageGrax(array $inTransit): array {

        return [
            "context" => $inTransit["wamid"],
            "preview_url" => false,
            "body" => "👏 Listo!! Mil Gracias...\n\nCotización en Valoración.\n\n🤝🏻 Éxito en tus Ventas!!."
        ];
        
    }

    ///
    public function isValid(array $message, array $fileCot): String {

        if(array_key_exists('type', $message)) {

            if(array_key_exists('body', $message[ $message['type'] ])) {
                
                $deta = $message[ $message['type'] ]['body'];
                if(strlen($deta) < 3) {
                    return 'notCosto';
                }

                $isNum = new ValidatorsMsgs();
                if(!$isNum->isValidNumero($deta)) {
                    return 'notDigit';
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
                "body" => "📝 Se esperaba el *Costo* de la Pieza.\n\n🚗 Cotización en Curso..."
            ],
            'notCosto' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "⚠️ El Costo no es válido, se más específico por favor."
            ],
            'notDigit' => [
                "context" => $inTransit["wamid"],
                "preview_url" => false,
                "body" => "⚠️ El Costo no es válido, Escribe sólo números por favor."
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
        $message['step'] = 'costo';
        $message['fileToCot'] = $this->pathToCot;
        return $message;
    }
}