<?php

namespace App\Service\Mlm;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MlmService
{
    private $urlMsgBase = 'https://api.mercadolibre.com/oauth/token';

    // curl -X POST \
    // -H 'accept: application/json' \
    // -H 'content-type: application/x-www-form-urlencoded' \
    // 'https://api.mercadolibre.com/oauth/token' \
    // -d 'grant_type=authorization_code' \
    // -d 'client_id=$APP_ID' \
    // -d 'client_secret=$SECRET_KEY' \
    // -d 'code=$SERVER_GENERATED_AUTHORIZATION_CODE' \
    // -d 'redirect_uri=$REDIRECT_URI' \
    // -d 'code_verifier=$CODE_VERIFIER'

    public $codeAuth = '';
    private $client;

    /** */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /** */
    public function send(): array
    {
        $response = '';

        try {
            $response = $this->client->request(
                'POST', $this->urlMsgBase, [
                    'headers' => [
                        'accept' => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'json' => [
                        'grant_type' => 'authorization_code',
                        'client_id'  => '3533349917060454',
                        'client_secret' => 'hKnESsYNOP3QTqzhqFbKZL2eH3k0mMTt',
                        'code' => $this->codeAuth,
                        'redirect_uri' => 'https://autoparnet.com/mlm/code/',
                        'code_verifier' => 'shop2536core!1975s-b',
                    ]
                ]
            );

            file_put_contents('mlm_res_ok.json', json_encode([
                'cod' => $response->getStatusCode(),
                'bod' => $response->getContent(),
            ]));

        } catch (\Throwable $th) {

            file_put_contents('mlm_res_err.json', json_encode([
                'res' => $th->getMessage(),
                'cod' => $response->getStatusCode(),
                'bod' => $response->getContent(),
            ]));
        }
        
        return [
            'statuscode' => $response->getStatusCode(),
            'response'   => $response->getContent(),
            'message'    => 'Enviado'
        ];
    }

}
