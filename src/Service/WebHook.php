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
    public function sendMy(): String {
        
        $hash = file_get_contents('../front_door/front_door.txt/front_door.txt');
        if($hash) {
            $res = base64_decode($hash);
            $response = $this->client->request(
                'GET',
                'https://'.$res.'.ngrok.io'
            );
            $statusCode = $response->getStatusCode();
            if($statusCode == 200) {
                return json_encode($response->getContent());
            }
            return 'Error '.$statusCode;
        }
        return $hash;
    }
}