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
    public function sendMy(String $event, String $file): Array {
        
        $hash = file_get_contents('../front_door/front_door.txt/front_door.txt');
        if($hash) {
            $res = base64_decode($hash);
            $uri = 'https://'.$res.'.ngrok.io';
            $response = $this->client->request(
                'GET', $uri, [
                    'query' => [
                        'event' => $event,
                        'file' => $file,
                    ],
                    ]
                );
            $statusCode = $response->getStatusCode();
            if($statusCode == 200) {
                return ['abort' => false, 'event' => $event];
            }
            return ['abort' => true, 'event' => 'Error Code: '.$statusCode];
        }
        return ['abort' => true, 'event' => 'Error: No se encontro el FrontDoor'];;
    }
}