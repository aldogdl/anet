<?php

namespace App\Service\WapiResponse;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ConmutadorWa
{

    private $uriBase = 'https://graph.facebook.com/v17.0/';
    private $client;

    public array $bodyToSend;

    /** */
    public function __construct(array $message, String $path)
    {
        $fileConm = file_get_contents($path);
        if($fileConm) {
            $content = json_encode($fileConm, true);
            
        }
    }

}
