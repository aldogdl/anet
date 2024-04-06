<?php

namespace App\Service\WapiProcess;

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
    public function send(ConmutadorWa $conm): array
    {
        $error = 'No se recibió cuerpo de mensaje valido para enviar.';
        $code  = 501;

        $this->wrapBody($conm->to, $conm->type, $conm->body);
        
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
                file_put_contents('wa_el_template_m_'.$code.'.json', json_encode([
                    'headers' => [
                        'Authorization' => 'Bearer '.$conm->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $this->bodyToSend
                ]));
            } catch (\Throwable $th) {
                $code = 401;
                if(mb_strpos($th->getMessage(), '401') !== false) {
                    $body = ['error' => 'Token de Whatsapp API caducado'];
                }else{
                    $body = ['error' => $th->getMessage()];
                }
                file_put_contents('wa_el_template_'.$code.'.json', json_encode($body));
            }
        }

        return [
            'statuscode' => $code,
            'body'   => ($body == '') ? $error : $body,
        ];
    }

    /** */
    private function wrapBody(String $to, String $type, array $body): void
    {
        $context = '';
        if(array_key_exists('context', $body)) {
            $context = $body['context'];
            unset($body['context']);
        }

        $this->bodyToSend = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"   => $to,
            "type" => $type,
            $type  => $body[$type]
        ];
 
        if($context != '') {
            $this->bodyToSend['context'] = ['message_id' => $context];
        }

    }

}
