<?php

namespace App\Service\Mlm;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MlmService
{
    private $urlMsgBase = 'https://api.mercadolibre.com/oauth/token';

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
        $response = $this->client->request('POST', $this->urlMsgBase, [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/x-www-form-urlencoded'
            ],
            'json' => [
                'grant_type' => 'authorization_code',
                'client_id'  => '3533349917060454',
                'client_secret' => 'hKnESsYNOP3QTqzhqFbKZL2eH3k0mMTt',
                'code' => $this->codeAuth,
                'redirect_uri' => 'https://autoparnet.com/mlm/code/',
                'code_verifier' => 'shop2536core!1975s-b'
            ]
        ]);

        
        file_put_contents('mlm_res_otro.json', json_encode([
            'cod' => $response->getContent(false),
            'hed' => $response->getHeaders(false),
            'grant_type' => 'authorization_code',
            'client_id'  => '3533349917060454',
            'client_secret' => 'hKnESsYNOP3QTqzhqFbKZL2eH3k0mMTt',
            'code' => $this->codeAuth,
            'redirect_uri' => 'https://autoparnet.com/mlm/code/',
            'code_verifier' => 'shop2536core!1975s-b'
        ]));

        // if($response->getStatusCode() == 200) {
            
        //     return [
        //         'statuscode' => $response->getStatusCode(),
        //         'response'   => $response->getContent(),
        //         'message'    => 'Enviado'
        //     ];

        // }else{

        //     file_put_contents('mlm_res_err.json', json_encode([
        //         'cod' => $response->getStatusCode(),
        //         'bod' => $response->getContent(),
        //     ]));
        // }

        return [];
    }

}
