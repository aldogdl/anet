<?php

namespace App\Service\WA;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\WA\BuildPayloadMsg;
use App\Service\WA\Dom\WaEntity;
use App\Service\WA\Dom\WaExtract;

class WaService
{
    private $token = '';
    private $client;
    private $urlMsg;
    private $payloads;

    public WaEntity $msg;

    /** */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->payloads = new BuildPayloadMsg();
    }
   
    /** */
    private function hidratarEnity(array $message) {

        $this->msg = new WaEntity($message);
        $this->urlMsg = 'https://graph.facebook.com/v16.0/'.
            $this->msg->acount->phoneNumberId .'/messages';
    }

    /** */
    public function getFileOrden(String $pathFile): array
    {
        return json_decode( file_get_contents($pathFile), true );
    }

    /** */
    public function isReaded(String $pathMsg)
    {
        $arch = json_decode( file_get_contents($pathMsg), true );
        if($arch) {
            $this->hidratarEnity($arch);
        }
        // $data = $this->payloads->bodyReader($arch);
        dd($this->msg->toArray());
        // $this->send();
    }

    /** */
    public function send()
    {
        $response = $this->client->request(
            'POST', $this->urlMsg, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => []
            ]
        );
        
        $statusCode = $response->getStatusCode();
        if($statusCode != 200) {

            $filename = round(microtime(true) * 1000);
            file_put_contents(
                'fails/'.$filename.'.json',
                json_encode([
                    'status' => $statusCode,
                    'razon'  => $response->getContent(),
                    'body'   => []
                ])
            );
            return false;
        }
    }
}
