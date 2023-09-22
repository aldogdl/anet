<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WebHook
{
    private $client;
    private $anetToken;
    private $sendMyFail;

    public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
    {
        $this->client = $client;
        $this->sendMyFail = $container->get('sendMyFail');
        $this->anetToken  = $container->get('getAnToken');
    }

    /** */
    public function sendMy(String $uriCall, String $pathFileServer, array $event): bool {

        $uri = $this->getUrlToFrontDoor();

        if($uri != '') {

            $proto = $this->buildProtocolo($uriCall, $pathFileServer, $event);

            $response = $this->client->request(
                'POST', $uri, [
                    'query' => ['anet-key' => $this->anetToken],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $proto
                ]
            );

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

    /** */
    public function getUrlToFrontDoor(): String
    {
        $hash = file_get_contents('../front_door/front_door.txt/front_door.txt');
        if($hash) {
            return base64_decode($hash);
        }
        return '';
    }

    /** */
    private function setDataFromWhatsapp(array $proto, array $data): array
    {
        // antes era: evento: wa_message
        $event = 'whatsapp_api';
        if(array_key_exists('subEvento', $data)) {
            if($data['subEvento'] == 'stt') {
                $event = 'statuses';
            }
        }

        $proto['evento'] = $event;
        $proto['from'] = $data['from'];
        return $proto;
    }

    /** */
    private function setDataFromShopCore(array $proto, array $data): array
    {
        if(array_key_exists('own', $data)) {
            $proto['from'] = $data['own']['slug'];
        }

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

    ///
    private function buildProtocolo(String $uriCall, String $pathFileServer, array $data): array
    {
        $date = new \DateTime('now');
        $protocolo = [
            'evento'    => 'unknow',
            'from'      => '',
            'uriCall'   => $uriCall,
            'srcServer' => $pathFileServer,
            'creado'    => $date->format('Y-m-d h:i:s'),
            'payload'   => $data
        ];
        
        if(mb_strpos($uriCall, 'convFree') !== false) {
            $protocolo['evento'] = 'convFree';
            $protocolo['from']   = $data['from'];
        }
        if(mb_strpos($uriCall, 'shop-core') !== false) {
            $protocolo = $this->setDataFromShopCore($protocolo, $data);
        }
        if(mb_strpos($uriCall, 'wa-wh') !== false) {
            $protocolo = $this->setDataFromWhatsapp($protocolo, $data);
        }
        if(mb_strpos($uriCall, 'wa-wh-err') !== false) {
            $protocolo['evento'] = 'error_whatsapp_api';
            $protocolo['from'] = 'S.R. Autoparnet';
        }
        if(mb_strpos($uriCall, 'make-test') !== false) {
            $protocolo['evento'] = $pathFileServer;
            $protocolo['from']   = 'Autoparnet';
        }
        if(mb_strpos($uriCall, 'enviar-orden') !== false) {
            $protocolo['evento'] = 'creada_solicitud';
            $protocolo['from']   = 'old-System';
        }
        if(mb_strpos($uriCall, 'cotiza-simula-sol') !== false) {
            $protocolo['evento'] = 'creada_solicitud';
            $protocolo['from']   = 'old-System';
        }

        return $protocolo;
    }

}