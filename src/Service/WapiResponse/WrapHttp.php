<?php

namespace App\Service\WapiResponse;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WrapHttp
{

    private $client;

    public array $bodyToSend = [];

    /** */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /** */
    public function send(ConmutadorWa $conm, bool $isReply = false): array
    {
        $error = 'No se recibió cuerpo de mensaje valido para enviar.';
        $code  = 501;

        $this->wrapBody($conm->to, $conm->type, $conm->body, $isReply);
        
        $body = '';
        if(count($this->bodyToSend) != 0) {

            try {
                $response = $this->client->request(
                    'POST', $conm->uriBase.'/messages', [
                        'headers' => [
                            'Authorization' => 'Bearer '.$conm->token,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $this->bodyToSend
                    ]
                );
                $code = $response->getStatusCode();
                $body = json_decode($response->getContent(), true);
            } catch (\Throwable $th) {
                $code = 401;
                if(mb_strpos($th->getMessage(), '401') !== false) {
                    $body = ['error' => 'Token de Whatsapp API caducado'];
                }else{
                    $body = ['error' => 'Error de Whatsapp desconocido'];
                }
            }
        }

        return [
            'statuscode' => $code,
            'body'   => ($body == '') ? $error : $body,
        ];
    }

    /** */
    private function wrapBody(String $to, String $type, array $body, bool $isReply): void
    {
        $context = '';
        if($isReply) {
            if(array_key_exists('context', $body)) {
                $context = $body['context'];
                unset($body['context']);
            }
        }

        $this->bodyToSend = [
            "to"   => $to,
            "type" => $type,
            $type  => $body,
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
        ];
 
        if($context != '') {
            $this->bodyToSend['context'] = ['message_id' => $context];
        }

    }

}
