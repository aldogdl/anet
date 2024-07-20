<?php

namespace App\Service\AnetTrack;

use App\Dtos\ConmDto;
use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\Fsys;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\EventDispatcher\Event;

class WaSender
{
    private $client;
    private ?ConmDto $conm;
    private array $body;
    private String $type;
    private $anetToken;
    private $sseFails;
    private $cnxFile;
    private bool $isTest;
    private String $reporTo = '523320396725';

    public Fsys $fSys;
    public String $context;
    public String $wamidMsg;
    /** La version del archivo de rutas */
    public String $sseNotRouteActive = '';
    
    /** */
    public function __construct(HttpClientInterface $client, ParameterBagInterface $container, Fsys $fsys)
    {
        $this->client   = $client;
        $this->fSys     = $fsys;
        $this->sseFails = $container->get('sseFails');
        $this->cnxFile  = $container->get('cnxFile');
        $this->context  = '';
        $this->anetToken= base64_encode($container->get('getAnToken'));
        $this->sseNotRouteActive= $container->get('sseNotRouteActive');
    }

    /** */
    public function setConmutador(WaMsgDto $waMsg): void
    {
        $this->isTest = $waMsg->isTest;
        try {
            $this->conm = new ConmDto($this->fSys->getConmuta());
            $this->conm->setMetaData($waMsg);
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
        if($error != '') {
            return $code;
        }

        $url = $this->conm->uriToWhatsapp.'/messages';
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
            $this->sendReporErrorBySendToWa($url, $code, $error, $this->body);
        }

        return $code;
    }

    /** */
    public function sendMy(array $event): bool
    {
        $code  = 505;
        $toUrl = 'http://to-comcore.info';
        $this->isTest = false;

        $headers = [];
        $forDown = false;
        if(array_key_exists('header', $event)) {
            $headers = $event['header'];
            unset($event['header']);
            if(array_key_exists('Anet-Down', $headers)) {
                $forDown = true;
            }
        }
        
        if(count($event) == 0) {
            $this->sendReporErrorBySendMy(
                $headers, $toUrl, $code, 'El cuerpo del mensaje resulto bacio, nada para enviar'
            );
            return false;
        }

        $error = "";
        $rutas = [];
        $error = 'No hay ruta activa hacia ComCore';
        $cnxFile = $this->getCnxFile();
        if(array_key_exists('routes', $cnxFile)) {
            $rutas = $cnxFile['routes'];
        }
        if(array_key_exists('version', $cnxFile)) {
            $headers = HeaderDto::cnxVer($headers, $cnxFile['version']);
            $cnxFile['version'];
        }
        $cant = count($rutas);
        if($cant == 0) {
            $this->sendReporErrorBySendMy($headers, $toUrl, 506, $error);
            return false;
        }
        
        $timeOut = 20;
        if($cant > 1) {
            $timeOut = $timeOut / $cant;
            $timeOut = floor($timeOut);
            $timeOut = max($timeOut, 3);
        }

        $headers['Content-Type'] = 'application/json';
        
        $error = 'Error inesperado al enviar mensaje a ComCore';
        $erroresSend = [];
        $rutaSend = [];
        if($this->isTest) {
            file_put_contents('test_sendMy_'.$this->conm->to.'.json', json_encode($event));
        }else{
            
            for ($i=0; $i < $cant; $i++) {
                
                // El dato de quien esta conectado a FTP
                $filename = $rutas[$i]['host'].'_'.$rutas[$i]['user'].'.json';
                $sessFtp = file_get_contents($filename);
                if($sessFtp) {
                    $sessFtp = json_decode($sessFtp, true);
                    $initTime = $sessFtp['init'];
                    $duration = $sessFtp['duration'] * 1000;
                    $expirationTime = $initTime + $duration;
                    $currentTime = microtime(true) * 1000;
                    // Calcular el tiempo restante para la caducidad
                    $timeRemaining = $expirationTime - $currentTime;
                    if ($timeRemaining < 0) {
                        $timeRemaining = 0;
                    }
                    $headers = HeaderDto::cnxVer($headers, $cnxFile['version']);
                }
                
                $dataReq = [
                    'query'   => ['anet-key' => $this->anetToken],
                    'timeout' => $timeOut,
                    'headers' => $headers,
                ];

                // Si forDown (para bajar) es false incluimos los datos en el body
                if(!$forDown) {
                    $dataReq['json'] = $event;
                }
                
                try {
                    $response = $this->client->request('POST', $rutas[$i]['url'], $dataReq);
                    $code = $response->getStatusCode();
                } catch (\Throwable $th) {
                    $toUrl = $rutas[$i]['url'];
                    $erroresSend[] = [
                        'ruta' => $rutas[$i],
                        'error'=> $th->getMessage()
                    ];
                }
                if($code == 200) {
                    $rutaSend = $rutas[$i];
                    break;
                }
            }
        }

        if($code != 200) {
            $this->sendReporErrorBySendMy($headers, $toUrl, $code, $error, $erroresSend);
        }

        // la ruta exitosa la colocamos en el balance
        $this->setCnxFile($rutaSend);
        return true;
    }

    /** */
    public function setCnxFile(array $rutaSend): void
    {
        $rota = count($rutaSend);
        if(count($rutaSend) == 0) {
            return;
        }
        if(!array_key_exists('host', $rutaSend)) {
            return;
        }
        
        $cnxFile = json_decode(file_get_contents($this->cnxFile), true);
        $cnxFile['balance'][] = $rutaSend['host'];
        $cnxFile['balance'] = array_values(array_unique($cnxFile['balance']));
        file_put_contents($this->cnxFile, json_encode($cnxFile));
    }

    /** */
    public function getCnxFile(): array
    {
        $cnxFile = json_decode(file_get_contents($this->cnxFile), true);
        if(!array_key_exists('routes', $cnxFile)) {
            return [];
        }
        $rota = count($cnxFile['routes']);
        if($rota == 0) {
            return [];
        }
        
        // Para el balance de carga
        $hosts = array_column($cnxFile['routes'], 'host');
        $hosts = array_values(array_unique($hosts));
        $notUse = array_diff($hosts, $cnxFile['balance']);
        if(count($notUse) == 0) {
            // Si notUse es igual a cero es que ya use todos por lo tanto 
            // reiniciamos el conteo.
            $cnxFile['balance'] = [];
            $notUse = $hosts;
        }

        $tunnels = [];

        for ($r=0; $r < $rota; $r++) {

            // Tendrian que indicar que estan activos para tomarce como opcion
            if($cnxFile['routes'][$r]['active']) {
                if(in_array($cnxFile['routes'][$r]['host'], $notUse)) {

                    $url = $cnxFile['routes'][$r]['public'].'-'.$cnxFile['routes'][$r]['id'];
                    $tunnels[] = [
                        'url'  => 'https://'.$url.'.ngrok-free.app/com_core/sse',
                        'isPay'=> $cnxFile['routes'][$r]['isPay'],
                        'user' => $cnxFile['routes'][$r]['user'],
                        'host' => $cnxFile['routes'][$r]['host'],
                    ];
                }
            }
        }


        $cnxFile['routes'] = $tunnels;
        return $cnxFile;
    }

    /** */
    private function sendReporErrorBySendToWa(String $url, String $code, String $error, array $body) {

        // TODO evitar los reportes de error con los status

        $result = [
            'evento'     => 'POR HACER',
            'from'       => $this->conm->to,
            'method'     => 'sendToWa',
            'statusCode' => $code,
            'reason'     => $error,
            'payload'    => $body
        ];

        if(!is_dir($this->sseFails)) {
            mkdir($this->sseFails);
        }

        $filename = 'evento'.'_'.'waId';
        if($this->isTest) {
            file_put_contents($this->sseFails.'/test_sentToWa_error_'.$this->conm->to.'.json', json_encode($result));
        }else{
            file_put_contents($this->sseFails.'/'.$filename.'.json', json_encode($result));
        }

        $subMsg = "Se intentó enviar evento de SR hacia Whatsapp";

        $msg = "*ERROR SendToWa EN SR.*:\n\n".
        "*Evento*: "."Por HACER"."\n".
        "*Contacto*: ".$this->conm->to."\n".
        "*Código*: ".$code . "\n".
        "*Razón*: ".$error."\n".
        "*Path*:\n\n".
        $url."\n\n".
        "_".$subMsg."_";

        $this->sendText($msg, $this->reporTo);
    }

    /** */
    private function sendReporErrorBySendMy(array $headers, String $url, String $code, String $error, array $body = []) {

        $result = [
            'statusCode' => $code,
            'reason'     => $error,
            'headers'    => $headers
        ];
        if(count($body) > 0) {
            $result['payload'] = $body;
        }

        if(!is_dir($this->sseFails)) {
            mkdir($this->sseFails);
        }
        
        $filename = microtime(true) * 1000;
        if($this->isTest) {
            file_put_contents($this->sseFails.'/test_sentToWa_error_'.$filename.'.json', json_encode($result));
        }else{
            file_put_contents($this->sseFails.'/'.$filename.'.json', json_encode($result));
        }
        
        $msg = "*ERROR SendMy EN SR.*:\n\n".
        "*Código*: ".$code . "\n".
        "*Razón*: ".$error."\n".
        "*Path*:\n\n".
        $url."\n\n";
        
        $this->conm = new ConmDto($this->fSys->getConmuta());
        $this->sendText($msg, $this->reporTo);
    }

}
