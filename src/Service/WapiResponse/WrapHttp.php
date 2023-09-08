<?php

namespace App\Service\WapiResponse;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WrapHttp
{

    private $uriBase = 'https://graph.facebook.com/v17.0/';
    private $client;

    public array $bodyToSend;

    /** */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /** */
    public function wrapBody(String $to, String $type, array $body): void {

        $this->bodyToSend = [
            "to"   => $to,
            "type" => $type,
            $type  => $body,
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
        ];
    }

    /** */
    public function send(String $token): array
    {
        $error = 'No se recibio un cuerpo de mensaje valido para enviar.';
        $code  = 501;

        if(count($this->bodyToSend) != 0) {
            
            $response = $this->client->request(
                'POST', $this->uriBase, [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $this->bodyToSend
                ]
            );

            if($response->getStatusCode() == 200) {
                return [];
            }

            $code = $response->getStatusCode();
            $error = $response->getContent(); 
        }

        return [
            'statuscode' => $code,
            'response'   => $error,
            'message'    => $this->bodyToSend
        ];
    }
}