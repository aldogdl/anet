<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebHook
{

    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /** */
    public function sendMy(array $dataEvent, String $rootNifi, String $token): bool {

        $hash = file_get_contents('../front_door/front_door.txt/front_door.txt');
        if($hash) {

            $res = base64_decode($hash);
            $uri = 'https://'.$res.'.ngrok.io';
            $date = new \DateTime('now');
            $dataEvent['creado'] = $date->format('Y-m-d h:i:s');

            $response = $this->client->request(
                'POST', $uri, [
                    'query' => ['anet-key' => $token],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $dataEvent
                ]
            );
            
            $statusCode = $response->getStatusCode();
            if($statusCode != 200) {
                file_put_contents($rootNifi.'fails/'.(microtime()*1000).'.json', $dataEvent);
                return false;
            }
        }

        return true;
    }
}