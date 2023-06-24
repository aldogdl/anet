<?php

namespace App\Service\WA;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\WA\Dom\WaAcountEntity;
use App\Service\WA\Dom\WaEntity;

class WaService
{
    private $urlMsgBase = 'https://graph.facebook.com/v17.0/';

    private $token = 'EAACYKUGlPw0BAL5MvWiHlrMTaRrmZBcGLsYZAw6PszdRspxwYwNuXZCGZBnxR8QIkpAiA9z1HOruSA41ooPJiN5hx9mbDIGMUlG9ZBisnDdabG4a3MjrBiKHTEX0d4KSDZAHteV8SUwtfIIInocvkjmAnU6IiuUGTjOLpvKPMmcZBrYtVnlbTa8zAlEQsXyIMd9IuEtq3ATpHvsMCNvyBq9';
    private $client;
    private $urlMsg;

    public WaEntity $msg;

    /** */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
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
    public function msgText(String $to, String $msg, String $context = '', String $urlPreview = '') {

        $text = ['body' =>  $msg, 'preview_url' => false];
        if($urlPreview != '') {
            $text['preview_url'] = $urlPreview;
        }
        $body = $this->getBasicBody($to);
        $body['type'] = 'text';
        $body['text'] = $text;
        if($context != '') {
            $body['context'] = ['message_id' => $context];
        }
        $this->send($text);
    }

    /** */
    public function send(array $bodySend)
    {
        $response = $this->client->request(
            'POST', $this->urlMsg, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $bodySend
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

    /** */
    private function getBasicBody(String $to) {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            "to" => $to,
        ];
    }
    
    /** */
    private function hidratarAcount(array $message): WaAcountEntity
    {
        $acount = new WaAcountEntity($message);
        $this->urlMsg = $this->urlMsgBase.$acount->phoneNumberId .'/messages';
        return $acount;
    }

    /** */
    private function hidratarEnity(array $message)
    {
        $this->msg = new WaEntity($message);
        $this->urlMsg = $this->urlMsgBase.$this->msg->acount->phoneNumberId .'/messages';
    }
}
