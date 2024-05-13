<?php

namespace App\Service\AnetTrack;

use App\Dtos\ConmDto;
use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WaSender
{
    private $client;
    private ?ConmDto $conm;
    private array $body;
    private String $type;
    private $anetToken;
    private $sendMyFail;
    private $comCoreFile;
    private bool $isTest;
    
    public Fsys $fSys;
    public String $context;
    public String $wamidMsg;
    
    /** */
    public function __construct(HttpClientInterface $client, ParameterBagInterface $container, Fsys $fsys)
    {
        $this->client = $client;
        $this->fSys = $fsys;
        $this->sendMyFail = $container->get('sendMyFail');
        $this->anetToken  = $container->get('getAnToken');
        $this->comCoreFile= $container->get('comCoreFile');
        $this->context = '';
    }

    /** */
    public function setConmutador(WaMsgDto $waMsg): void
    {
        $this->isTest = $waMsg->isTest;
        try {
            $this->conm = new ConmDto($waMsg, $this->fSys->getConmuta());
        } catch (\Throwable $th) {
            $this->conm = null;
        }
    }

    /**
     * Usado para enviar msg de rastreo, es decir, aquellas plantillas que ya
     * estan armadas y no requieren de envoltura de otros campos.
    */
    public function sendTemplate(String $idItem): int
    {
        $tmp = $this->fSys->getContent('prodTrack', $idItem.'_track.json');
        if(count($tmp) > 0) {
            $tmp = $tmp['message'];
            $tmp['to'] = $this->conm->to;
        }
        $this->type = $tmp['type'];
        $this->body = $tmp;
        return $this->sendToWa();
    }

    /** 
     * Usado para enviar msg donde se enviar solo texto
    */
    public function sendText(String $texto): int
    {
        $this->type = 'text';
        $this->body = ['body' => $texto];
        $this->wrapBody();
        return $this->sendToWa();
    }

    /** 
     * Usado para enviar msg que bienen de las templates prefabricadas
    */
    public function sendPreTemplate(array $body): int
    {
        $this->type = $body['type'];
        $this->body = $body[$this->type];
        $this->wrapBody();
        return $this->sendToWa();
    }

    /** */
    private function sendToWa(): int
    {
        $error = 'No se recibió cuerpo de mensaje valido para enviar.';
        $code  = 501;
        $bodyResult = [];
        if($this->conm != null) {

            if($this->isTest) {
                file_put_contents('test_sentToWa_'.$this->conm->to.'.json', json_encode($this->body));
            }
            
            if(count($this->body) != 0 && $this->isTest === false) {
                
                try {
                    $response = $this->client->request(
                        'POST', $this->conm->uriBase . '/messages', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->conm->token,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => $this->body
                        ]
                    );
                    $code = $response->getStatusCode();
                    $bodyResult = json_decode($response->getContent(), true);
                    
                } catch (\Throwable $th) {
                    $code = 401;
                    if(mb_strpos($th->getMessage(), '401') !== false) {
                        $bodyResult = ['error' => 'Token de Whatsapp API caducado', 'razon' => $bodyResult];
                    }else{
                        $bodyResult = ['error' => $th->getMessage(), 'razon' => $bodyResult];
                    }
                }
            }
        }else{
            $error = 'El Archivo conmutador de SR. resulto nulo';
        }

        $this->wamidMsg = '';
        if($code >= 200 && $code <= 300) {
            if(array_key_exists('messages', $bodyResult)) {
                if(array_key_exists('id', $bodyResult['messages'][0])) {
                    $this->wamidMsg = $bodyResult['messages'][0]['id'];
                }
            }
        }else {
            $result = [
                'evento' => 'error_sr',
                'statuscode' => $code,
                'payload' => $bodyResult
            ];
            // Si ocurren un error al enviar el mesnaje por whatsapp
            // enviamos el error a EventCore.
            if($this->isTest) {
                file_put_contents('test_sentToWa_error_'.$this->conm->to.'.json', json_encode($result));
            }else{
                file_put_contents('wa_error_'.$this->conm->to.'.json', json_encode($result));
                $this->sendMy($result);
            }
        }

        return $code;
    }

    /** */
    private function wrapBody(): void
    {
        $this->body = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"   => $this->conm->to,
            "type" => $this->type,
            $this->type  => $this->body
        ];

        $context = ($this->context == '') ? $this->conm->context : $this->context;
        if($context != '') {
            $this->body['context'] = ['message_id' => $context];
        }
    }

    /** */
    public function sendMy(array $event): bool {

        $uri = $this->getUrlToCC();

        if($uri != '') {

            $statusCode = 500;
            if(!array_key_exists('evento', $event)) {
                $proto = $this->buildProtocolo($event);
            }else{
                $proto = $event;
            }

            if($this->isTest) {
                file_put_contents('test_sendMy_'.$this->conm->to.'.json', json_encode($proto));
            }else{

                try {
                    $response = $this->client->request(
                        'POST', $uri, [
                            'query' => ['anet-key' => $this->anetToken],
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
            }

            if($statusCode != 200) {

                $result = [
                    'evento' => 'error_sr',
                    'statuscode' => $statusCode,
                    'payload' => [
                        'body' => ($this->isTest) ? 'El error del Response' : $response->getContent()
                    ]
                ];

                $filename = round(microtime(true) * 1000);
                if(!is_dir($this->sendMyFail)) {
                    mkdir($this->sendMyFail);
                }
                file_put_contents($this->sendMyFail.$filename.'.json', json_encode($result));
                return false;
            }
        }

        return true;
    }

    ///
    private function buildProtocolo(array $data): array
    {
        $protocolo = ['evento' => 'unknow'];

        if(array_key_exists('eventName', $data)) {
            $protocolo['evento'] = $data['eventName'];
            unset($data['eventName']);
        }elseif(array_key_exists('action', $data)) {
            
            if($data['action'] == 'publik') {
                $protocolo['evento'] = 'creada_publicacion';
            }elseif($data['action'] == 'cotiza') {
                $protocolo['evento'] = 'creada_solicitud';
            }
            unset($data['action']);
        }
        
        $protocolo['payload'] = $data;
        return $protocolo;
    }

    /** */
    public function getUrlToCC(): String
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
