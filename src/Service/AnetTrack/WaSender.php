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
    private $sseFails;
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
        $this->sseFails = $container->get('sseFails');
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
     * Usado para enviar msg que vienen de las templates prefabricadas
    */
    public function sendPreTemplate(array $body): int
    {
        $this->type = $body['type'];
        $this->body = $body[$this->type];
        $this->wrapBody();
        return $this->sendToWa();
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
        $modo = 'master';
        $source = 'anet_shop';
        // Si la fuente no es de anet_shop entonces es de Whatsapp
        $source = ($evento != $source) ? 'whatsapp_api' : $source;

        $comCore = json_decode(file_get_contents($this->comCoreFile), true);
        
        if(!array_key_exists('event_route', $comCore)) {
            return [];
        }
        $this->routerVer = $comCore['version'];
            
        $rutas = $comCore['event_route'][$source];
        if(!array_key_exists($rutas[0], $comCore)) {
            return [];
        }

        $this->conm->setEventAndSegRoute($source, $rutas[0]);

        // Por default tomamo el primer destino
        $destinos = $comCore[$rutas[0]];
        $endPoint = [];
        // Tendrian que indicar que estan activos para tomarce como opcion
        if($destinos[0]['active'] == 'this') {
            $url = $destinos[0]['public'].'-'.$destinos[0]['id'];
            $endPoint = ['url' => 'https://'.$url.'.ngrok-free.app/com_core/sse', 'modo' => $modo];
        }
        
        return $endPoint;
    }

    /** 
     * Enviamos un mensaje a whatsapp para que le llegue a un Contacto.
     * NOTA: Este mensaje retorna hasta 3 mensajes de status.
     * POSIBLES ERRORES:
     * a) Token caducado 401
     * b) Mal Número de teléfono 400
    */
    private function sendToWa(): int
    {
        $code  = 504;
        $bodyResult = [];
        $this->wamidMsg = '';
        $error = ($this->conm == null)
            ? 'El Archivo conmutador de SR. resulto nulo'
            : '';
        if(count($this->body) == 0) {
            $error = 'El cuerpo del mensaje resulto bacio, nada para enviar';
        }
        
        $url = $this->conm->uriBase.'/messages';
        $this->conm->setEventAndSegRoute('whatsapp_api', 'anet_track');

        if($error == '') {

            if($this->isTest) {
                file_put_contents('test_sentToWa_'.$this->conm->to.'.json', json_encode($this->body));
            }else{
                try {
                    $response = $this->client->request(
                        'POST', $url, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->conm->token,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => $this->body
                        ]
                    );
    
                    $code = $response->getStatusCode();
                    if($code >= 200 && $code <= 300) {
                        $code = 200;
                        $bodyResult = json_decode($response->getContent(), true);
                        if(array_key_exists('messages', $bodyResult)) {
                            if(array_key_exists('id', $bodyResult['messages'][0])) {
                                $this->wamidMsg = $bodyResult['messages'][0]['id'];
                            }
                        }
                    }else {
                        $error = $response->getContent();
                    }
    
                } catch (\Throwable $th) {
    
                    $error = $th->getMessage();
                    if(mb_strpos($error, '401') !== false) {
                        $error = 'Token de Whatsapp API caducado';
                    }else if(mb_strpos($error, '400') !== false) {
                        $error = 'Mensaje mal formado';
                    }else if(mb_strpos($error, 'timeout') !== false) {
                        $error = 'Se superó el tiempo de espera';
                    }
                }
            }
        }

        if($code > 200) {
            $this->prepareError('sendToWa', $url, $code, $error, $this->body);
        }

        return $code;
    }

    /** */
    public function sendMy(array $event): bool
    {
        $code  = 505;
        $this->isTest = false;
        $error = ($this->conm == null)
            ? 'El Archivo conmutador de SR. resulto nulo'
            : '';
        if(count($event) == 0) {
            $error = 'El cuerpo del mensaje resulto bacio, nada para enviar';
        }

        if($error == '') {

            if(!array_key_exists('evento', $event)) {
                $proto = $this->buildProtocolo($event);
            }else{
                $proto = $event;
            }
            $proto['routerVer'] = $this->routerVer;
    
            $uri = $this->getUrlsToCC($proto['evento']);
            if(count($uri) == 0) {
                $this->prepareError('sendMy', 'http://desconocida.info', 506, 'No hay ruta activa hacia ComCore', $proto);
                return true;
            }
    
            $proto['modo'] = $uri['modo'];
            $error = 'Error inesperado al enviar mensaje a ComCore';

            if($this->isTest) {
                file_put_contents('test_sendMy_'.$this->conm->to.'.json', json_encode($proto));
            }else{
    
                try {
                    $response = $this->client->request(
                        'POST', $uri['url'], [
                            'query' => ['anet-key' => $this->anetToken],
                            'timeout' => 21,
                            'headers' => [
                                'Content-Type' => 'application/json',
                            ],
                            'json' => $proto
                        ]
                    );
                    $code = $response->getStatusCode();
                } catch (\Throwable $th) {
                    $error = $th->getMessage();
                }
            }
    
            if($code != 200) {
                $this->prepareError('sendMy', $uri['url'], $code, $error, $proto);
            }
        }

        return true;
    }

    /** */
    private function prepareError(String $method, String $url, String $code, String $error, array $body) {

        if($this->conm->subEvento == 'stt') {
            return;
        }

        $result = [
            'evento'     => $this->conm->evento,
            'subEvento'  => $this->conm->subEvento,
            'from'       => $this->conm->to,
            'method'     => $method,
            'statusCode' => $code,
            'reason'     => $error,
            'reportTo'   => $this->conm->sendReportTo,
            'payload'    => $body
        ];

        if(!is_dir($this->sseFails)) {
            mkdir($this->sseFails);
        }

        $filename = $this->conm->subEvento.'_'.$this->conm->to;
        if($this->isTest) {
            file_put_contents($this->sseFails.'/test_sentToWa_error_'.$this->conm->to.'.json', json_encode($result));
        }else{
            file_put_contents($this->sseFails.'/'.$filename.'.json', json_encode($result));
        }

        // Si el error es por latencia hacia ngrok, no intentamos enviar el reporte del error a ComCore
        // ya que por esta razon misma se produjo el error.
        if(mb_strpos($error, 'tiempo') === false && $method != 'sendMy') {
            $this->sendMy($result);
        }

        $msg = "ERROR SENDER EN SR.:\n".
        "Evento: ".$this->conm->evento."\n".
        "SubEvento: ".$this->conm->subEvento."\n".
        "Contacto: ".$this->conm->to."\n".
        "Código: ".$code . "\n".
        "Razón: ".$error."\n".
        "Path:\n\n".
        $url;

        $this->sendText($msg, $this->conm->sendReportTo);
    }

}
