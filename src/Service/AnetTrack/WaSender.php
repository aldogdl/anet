<?php

namespace App\Service\AnetTrack;

use App\Dtos\ConmDto;
use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
    /** La version del archivo de rutas */
    public String $routerVer = '';
    /** La version del archivo de rutas */
    public String $sseNotRouteActive = '';
    
    /** */
    public function __construct(HttpClientInterface $client, ParameterBagInterface $container, Fsys $fsys)
    {
        $this->client = $client;
        $this->fSys = $fsys;
        $this->sendMyFail = $container->get('sendMyFail');
        $this->comCoreFile= $container->get('comCoreFile');
        $this->sseNotRouteActive= $container->get('sseNotRouteActive');
        $this->anetToken  = base64_encode($container->get('getAnToken'));
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
            $this->type = $tmp['type'];
            $this->body = $tmp;
            return $this->sendToWa();
        }
    }

    /** 
     * Usado para enviar msg donde se enviar solo texto
    */
    public function sendText(String $texto, String $para = ''): int
    {
        $this->type = 'text';
        $this->body = ['body' => $texto];
        $this->wrapBody($para);
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
        $code  = 503;
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
                    $code = 504;
                    if(mb_strpos($th->getMessage(), '401') !== false) {
                        $bodyResult = ['razon' => 'Token de Whatsapp API caducado', 'body' => $this->body];
                    }else{
                        $bodyResult = ['razon' => $th->getMessage(), 'body' => $this->body];
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
                'reason' => $bodyResult
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
    private function wrapBody(String $para = ''): void
    {
        $this->body = [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"   => ($para == '') ? $this->conm->to : $para,
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

        $statusCode = 500;
        $proto = $event;
        $this->isTest = false;

        if(!array_key_exists('evento', $event)) {
            $proto = $this->buildProtocolo($event);
        }else{
            file_put_contents('si_existe_evento'.'.json', json_encode($event));
        }

        $uri = $this->getUrlsToCC($proto['evento']);
        $rota = count($uri);
        $proto['routerVer'] = $this->routerVer;

        if($rota == 0) {
            // Si no se encuentran rutas a ComCore creamos una archivo
            $path = $this->sseNotRouteActive.'/'.$proto['evento'];
            if(!file_exists($path)) {
                mkdir($path, 0664, true);
            }
            file_put_contents(
                $path.'/'.round(microtime(true) * 1000).'json',
                json_encode($proto)
            );
            return true;
        }

        for ($i=0; $i < $rota; $i++) {

            $proto['modo'] = $uri[$i]['modo'];
            $msgResults = 'Error inesperado';
            try {
                $response = $this->trySend($uri[$i]['url'], $proto);
                if(!is_string($response)) {
                    $statusCode = $response->getStatusCode();
                    $msgResults = $response->getContent();
                }else{
                    $statusCode = 502;
                    $msgResults = $response;
                }
            } catch (\Throwable $th) {
                $statusCode = 501;
                $msgResults = $th->getMessage();
            }

            if($statusCode != 200) {
                $result = [
                    'evento' => 'error_sr',
                    'statuscode' => $statusCode,
                    'reason' => ($this->isTest) ? 'El error del Response' : $msgResults,
                    'payload' => $proto
                ];
    
                $filename = $proto['evento'].'_'.$event['from'];
                if(!is_dir($this->sendMyFail)) {
                    mkdir($this->sendMyFail);
                }
                // si hay un error en lugar de tratar de enviarle el mensaje a ComCore Slave
                // retornamos true para que whatsapp no este reeneviando el mismo mensaje.
                if($proto['payload']['subEvent'] != 'stt') {
                    file_put_contents($this->sendMyFail.$filename.'.json', json_encode($result));
                    $msg = "ERROR EN SR.: ". $proto['payload']['subEvent'] .
                    " Código: ".$statusCode."\n".
                    "Razón: ".$msgResults;
                    $this->sendText($msg, '523320396725');
                }
            }
            break;
        }

        return true;
    }

    /** */
    private function trySend(String $uri, array $proto): ResponseInterface | String
    {
        if($this->isTest) {
            file_put_contents('test_sendMy_'.$this->conm->to.'.json', json_encode($proto));
        }else{

            try {
                $response = $this->client->request(
                    'POST', $uri, [
                        'query' => ['anet-key' => $this->anetToken],
                        'timeout' => 21,
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $proto
                    ]
                );
            } catch (\Throwable $th) {
                return 'error::'.$th->getMessage();
            }
            return $response;
        }

        return 'Error no capturado';
    }

    ///
    private function buildProtocolo(array $data): array
    {
        $protocolo = ['evento' => 'unknow'];

        if(array_key_exists('eventName', $data)) {
            $protocolo['evento'] = $data['eventName'];
            unset($data['eventName']);
        }
        $protocolo['payload'] = $data;
        return $protocolo;
    }

    /** */
    public function getUrlsToCC(String $evento): array
    {
        $opc = [];
        $segmento = 'anet_shop';
        $segmento = ($evento != $segmento) ? 'whatsapp_api' : $segmento;
        
        $comCore = json_decode(file_get_contents($this->comCoreFile), true);
        
        if(array_key_exists('event_route', $comCore)) {
            
            $this->routerVer = $comCore['version'];
            
            $storageUrl = [];
            $rutas = $comCore['event_route'][$segmento];
            $rota = count($rutas);
            for ($i=0; $i < $rota; $i++) { 
                
                if(array_key_exists($rutas[$i], $comCore)) {

                    // Desde la app de ComCore este orden es que todas las que sean PROD
                    // se colocan al inicio del array, por lo tanto la primera sera master
                    $modo = ($i == 0) ? 'master' : 'slave';

                    $destinos = $comCore[ $rutas[$i] ];
                    $vueltas = count($destinos);
                    // Por default tomamo el primer destinp
                    $endPoint = $destinos[0];
                    // En dado caso de que halla mas destinos en el mismo segmento de rutas
                    if($vueltas > 1) {
                        if($endPoint['env'] != 'prod') {
                            // Buscamos entre estos solo aquellos que sean ENV: prod
                            $has = array_search('prod', array_column($destinos, 'env'));
                            if($has !== false) {
                                $endPoint = $destinos[$has];
                            }
                        } else {
                            if($endPoint['active'] == 'none') {
                                $has = array_search('this', array_column($destinos, 'active'));
                                if($has !== false) {
                                    $endPoint = $destinos[$has];
                                }
                            }
                        }
                    }

                    // Tendrian que indicar que estan activos para tomarce como opcion
                    if($endPoint['active'] == 'this') {
                        $url = $endPoint['public'].'-'.$endPoint['id'];
                        if(in_array($url, $storageUrl) === false) {
                            $opc[] = ['url' => 'https://'.$url.'.ngrok-free.app/com_core/sse', 'modo' => $modo];
                        }
                        $storageUrl[] = $url;
                    }
                }
            }
        }

        return $opc;
    }

}
