<?php

namespace App\Service\WA;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\WA\Dom\WaAcountEntity;
use App\Service\WA\Dom\WaEntity;

class WaService
{
    private $urlMsgBase = 'https://graph.facebook.com/v17.0/';

    private $token = '';
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
    public function msgText(
        String $to, String $msg, String $context = '', String $urlPreview = ''
    ): array {

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
        
        $result = $this->send($body);
        file_put_contents('result.json', json_encode($result));
        return ($result['statuscode'] != 200) ? $result : [];
    }

    /** */
    public function hidratarAcount(array $message, String $token): WaAcountEntity
    {   
        $tk = '';
        if(mb_strpos($token, 'aldo_') !== false) {
            $tk = str_replace('aldo_', '', $token);
        }
        $this->token = $tk;
        $acount = new WaAcountEntity($message);
        $this->urlMsg = $this->urlMsgBase.$acount->phoneNumberId .'/messages';
        return $acount;
    }

    /** */
    public function hidratarEnity(array $message)
    {
        $this->msg = new WaEntity($message);
        $this->urlMsg = $this->urlMsgBase.$this->msg->acount->phoneNumberId .'/messages';
    }

    /** */
    public function send(array $bodySend): array
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

        return [
            'statuscode' => $response->getStatusCode(),
            'response'   => $response->getContent(),
            'message'    => $bodySend
        ];
    }

    /** */
    private function getBasicBody(String $to) {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            "to" => $to,
        ];
    }

}
