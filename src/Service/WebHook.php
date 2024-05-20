<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WebHook
{
    private $client;
    private $anetToken;
    private $sendMyFail;
    private $comCoreFile;

    public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
    {
        $this->client = $client;
        $this->sendMyFail = $container->get('sendMyFail');
        $this->anetToken  = $container->get('getAnToken');
        $this->comCoreFile= $container->get('comCoreFile');
    }

    /** */
    public function sendMy(String $uriCall, String $pathFileServer, array $event): bool {

        $uri = $this->getUrlToFrontDoor();

        if($uri != '') {

            $proto = $this->buildProtocolo($uriCall, $pathFileServer, $event);
            $tok = base64_encode($this->anetToken);

            try {
                $response = $this->client->request(
                    'POST', $uri, [
                        'query' => ['anet-key' => $tok],
                        'timeout' => 120.0,
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $proto
                    ]
                );
            } catch (\Throwable $th) {
                return false;
            }

            $statusCode = $response->getStatusCode();
            if($statusCode != 200) {

                $filename = round(microtime(true) * 1000);
                $proto['statusCode'] = $statusCode;
                $proto['motive'] = $response->getContent();
                if(!is_dir($this->sendMyFail)) {
                    mkdir($this->sendMyFail);
                }
                file_put_contents($this->sendMyFail.$filename.'.json', json_encode($proto));
                return false;
            }
        }

        return true;
    }

    ///
    private function buildProtocolo(String $uriCall, String $pathFileServer, array $data): array
    {
        $date = new \DateTime('now');
        $protocolo = [
            'evento'    => 'unknow',
            'uriCall'   => $uriCall,
            'srcServer' => $pathFileServer,
            'creado'    => $date->format('Y-m-d h:i:s'),
            'payload'   => $data
        ];
        
        if(mb_strpos($uriCall, 'convFree') !== false) {
            $protocolo['evento'] = 'conv_free';
        }
        if(mb_strpos($uriCall, 'send-product-mlm') !== false) {
            $protocolo['evento'] = 'item_send_mlm';
        }
        if(mb_strpos($uriCall, 'anet-shop') !== false) {
            $protocolo = $this->setDataFromAnetShop($protocolo, $data);
        }
        if(mb_strpos($uriCall, 'wa-wh') !== false) {
            $protocolo['evento'] = 'whatsapp_api';
        }
        if(mb_strpos($uriCall, 'wa-wh-err') !== false) {
            $protocolo['evento'] = 'error_whatsapp_api';
        }
        if(mb_strpos($uriCall, 'ngrok') !== false) {
            $protocolo['evento'] = 'ngrok_event';
        }

        return $protocolo;
    }

    /** */
    private function setDataFromAnetShop(array $proto, array $data): array
    {
        if(array_key_exists('evento', $data)) {
            $proto['evento'] = $data['evento'];
        }

        if(array_key_exists('action', $data)) {
            
            if($data['action'] == 'publik') {
                $proto['evento'] = 'creada_publicacion';
            }
            if($data['action'] == 'cotiza') {
                $proto['evento'] = 'creada_solicitud';
            }
        }

        return $proto;
    }

    /** */
    public function getUrlToFrontDoor(): String
    {
        $comCore = file_get_contents($this->comCoreFile);

        if($comCore) {
            $comCore = json_decode($comCore, true);
            if(array_key_exists('getaways', $comCore)) {
                $comCore = $comCore['getaways'];
                $rota = count($comCore);
                for ($i=0; $i < $rota; $i++) { 
                    if($comCore[$i]['depto'] == 'event') {
                        return 'https://'. $comCore[$i]['public'] . '.ngrok-free.app';
                    }
                }
            }
        }
        return '';
    }

}