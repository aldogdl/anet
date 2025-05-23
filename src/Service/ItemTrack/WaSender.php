<?php

namespace App\Service\ItemTrack;

use App\Dtos\ConmDto;
use App\Dtos\HeaderDto;
use App\Dtos\WaMsgDto;
use App\Service\MyFsys;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WaSender
{
    private $client;
    public ?ConmDto $conm;
    private array $body;
    private String $type;
    private $anetToken;
    private $sseFails;
    private $cnxFile;
    private bool $isTest;
    private String $reporTo = '523316195698';

    public MyFsys $fSys;
    public String $context;
    public String $wamidMsg;
    public String $errFromWa;
    /** La version del archivo de rutas */
    public String $sseNotRouteActive = '';
    
    /** */
    public function __construct(HttpClientInterface $client, ParameterBagInterface $container, MyFsys $fsys)
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
    public function initConmutador(): void
    {
        $this->isTest = false;
        try {
            $this->conm = new ConmDto($this->fSys->getConmuta());
        } catch (\Throwable $th) {
            $this->conm = null;
        }
    }

    /** */
    public function setWaIdToConmutador(String $waId): bool
    {
        try {
            $this->conm->setWaId($waId);
        } catch (\Throwable $th) {
            return false;
        }
        return true;
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
    public function sendTemplate(String $idDbSr): int
    {
        $tmp = $this->fSys->getContent('itemTrack', $idDbSr.'_track.json');

        if(count($tmp) > 0) {
            $tmp = $tmp['message'];
            $tmp['to'] = $this->conm->to;
            $tmp[$tmp['type']]['footer']['text'] = 'Aumenta tu calificación de proveedor atendiendo este MSJ.👍';

            $this->type = $tmp['type'];
            $this->body = $tmp;
            return $this->sendToWa();
        }
        return 500;
    }

    /** 
     * Usado para enviar msg que vienen de las templates prefabricadas.
    */
    public function sendTemplateRememberLogin(): int
    {
        $this->type = 'template';
        $this->body = [
            'template' => [
                "name" => "recordatorio_ok",
                "language" => [
                    "code" => "es_MX"
                ]
            ]
        ];
        $this->wrapBody();
        return $this->sendToWa();
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
        $this->errFromWa = ($this->conm == null)
            ? 'El Archivo conmutador de SR. resulto nulo'
            : '';
        if(count($this->body) == 0) {
            $this->errFromWa = 'El cuerpo del mensaje resulto bacio, nada para enviar';
        }
        if($this->errFromWa != '') {
            return $code;
        }

        $url = $this->conm->uriToWhatsapp.'/messages';
        if($this->errFromWa == '') {

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
                        $this->errFromWa = $response->getContent();
                    }
    
                } catch (\Throwable $th) {
                    file_put_contents('wa_error_1'.$this->conm->to.'.json', json_encode([$th->getMessage()]));
                    file_put_contents('wa_error_2'.$this->conm->to.'.json', json_encode([$response->getContent()]));
                    $this->errFromWa = $th->getMessage();
                    if(mb_strpos($this->errFromWa, '401') !== false) {
                        $this->errFromWa = 'Token de Whatsapp API caducado';
                    }else if(mb_strpos($this->errFromWa, '400') !== false) {
                        $this->errFromWa = 'Mensaje mal formado';
                    }else if(mb_strpos($this->errFromWa, 'timeout') !== false) {
                        $this->errFromWa = 'Se superó el tiempo de espera';
                    }
                }
            }
        }

        if($code > 200) {
            $this->sendReporErrorBySendToWa($url, $code, $this->errFromWa, $this->body);
        }

        return $code;
    }

    /** // TODO agregar whatsapp_api en la cabecera */
    public function sendMy(array $event): bool
    {
        $byMetodo = 'HEAD';
        $code  = 505;
        $toUrl = 'http://to-anettrack.info';
        $this->isTest = false;

        if(count($event) == 0) {
            $this->sendReporErrorBySendMy(
                $byMetodo, [], $toUrl, $code, 'El cuerpo del mensaje resultó bacio, nada para enviar'
            );
            return false;
        }

        // Extraemos las cabeceras para el request del evento
        $headers = [];
        $isForDownload = 0;
        if(array_key_exists('header', $event)) {
            $headers = $event['header'];
            unset($event['header']);
            if(array_key_exists('Anet-Down', $headers)) {
                $isForDownload = $headers['Anet-Down'];
            }
        }
        
        $rutas = [];
        $error = 'No hay ruta activa hacia AnetTrack';
        $cnxFile = $this->getCnxFile();
        if(array_key_exists('routes', $cnxFile)) {
            $rutas = $cnxFile['routes'];
        }
        $cant = count($rutas);
        if($cant == 0) {
            $this->sendReporErrorBySendMy($byMetodo, $headers, $toUrl, 506, $error);
            return false;
        }
        
        // Dividimos el tiempo(timeout) de espera entre la cantidad de rutas
        // existente dejando un mínimo de 3 segundos por cada ruta
        $timeOut = 20;
        if($cant > 1) {
            $timeOut = $timeOut / $cant;
            $timeOut = floor($timeOut);
            $timeOut = max($timeOut, 3);
        }

        $rutaSend = [];
        $erroresSend = [];
        $error = 'Error inesperado al enviar mensaje a AnetTrack';

        if($this->isTest) {
            file_put_contents('test_sendMy_'.$this->conm->to.'.json', json_encode($event));
        }else{
            
            if(array_key_exists('version', $cnxFile)) {
                $headers = HeaderDto::cnxVer($headers, $cnxFile['version']);
            }
            $headers = HeaderDto::anetKey($headers, $this->anetToken);
            $headers['Content-Type'] = 'application/json; charset=UTF-8';
            $dataReq = [
                'timeout' => $timeOut,
                'headers' => $headers,
            ];

            // Si isForDownload (para descargar el body) es 0 incluimos los datos en el body
            // ya que se esta diciendo que no es para descarga por lo tanto el body debe
            // llevar los datos para evitar tenerlos que descargar.
            if($isForDownload == 0) {
                $dataReq['json'] = $event;
                $byMetodo = 'POST';
            }

            // Guardamos un historial de los envios
            if(array_key_exists('Anet-Event', $headers)) {
                if($headers['Anet-Event'] != 'stt') {

                    $prefix = '/';
                    if(array_key_exists('Anet-Iddb', $headers)) {
                        $prefix = '/'.$headers['Anet-Iddb'].'_';
                    }
                    $filename = $prefix.$headers['Anet-Event'].'_'.$headers['Anet-WaId'];
                    $pathSendmy = $this->fSys->getFolderTo('waSendmy');
                    $dataReq['headers']['Anet-Backup'] = $filename;
                    file_put_contents($pathSendmy.$filename.'.json', json_encode([
                        'method' => $byMetodo,
                        'rutas'  => $rutas,
                        'content' => $dataReq
                    ]));
                }
            }
            
            for ($i=0; $i < $cant; $i++) {

                try {
                    $response = $this->client->request($byMetodo, $rutas[$i]['url'], $dataReq);
                    $code = $response->getStatusCode();
                    if($code != 200) {
                        $error = $response->getContent();
                    }
                } catch (\Throwable $th) {
                    $error = $th->getMessage();
                }
                
                $toUrl = $rutas[$i]['url'];
                if($code == 200) {
                    $error = '';
                    $rutaSend = $rutas[$i];
                    break;
                } else {
                    $erroresSend[] = ['ruta' => $rutas[$i], 'error'=> $error];
                }
            }
        }

        if($code != 200) {
            $this->sendReporErrorBySendMy($byMetodo, $headers, $toUrl, $code, $error, $erroresSend);
            return true;
        }

        // la ruta exitosa la colocamos en el balance
        $this->setCnxFile($rutaSend);
        return true;
    }

    /** */
    public function setCnxFile(array $rutaSend): void
    {
        if(count($rutaSend) == 0) {
            return;
        }
        if(!array_key_exists('host', $rutaSend)) {
            return;
        }

        $cnxFile = json_decode(file_get_contents($this->cnxFile), true);
        $existentes = $cnxFile['balance'];

        $cnxFile['balance'][] = $rutaSend['host'];
        $cnxFile['balance'] = array_values(array_unique($cnxFile['balance']));
        
        $resultante = $cnxFile['balance'];
        sort($existentes);
        sort($resultante);
        if($existentes != $resultante) {
            file_put_contents($this->cnxFile, json_encode($cnxFile));
        }
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
        // Tomamos los que no esten en los usados
        $notUsed = array_diff($hosts, $cnxFile['balance']);
        if(count($notUsed) == 0) {
            // Si notUse es igual a cero es que ya use todos por lo tanto 
            // reiniciamos el conteo.
            $cnxFile['balance'] = [];
            $notUsed = $hosts;
        }
        
        $priorys = ["polite", "cosmic"];
        $cantPriory = count($priorys);
        // Los tuneles que no se les ha enviado un mensaje
        $tunnels = [];
        // Los tuneles que ya fueron usados
        // [NOTA] aun asi se agregan por si los anteriores no responden
        $tunnelsAlt = [];
        $morePriory = [];

        for ($r=0; $r < $rota; $r++) {

            // Tendrian que indicar que estan activos para tomarce como opcion
            if(!$cnxFile['routes'][$r]['active']) {
                continue;
            }

            $url = $cnxFile['routes'][$r]['public'].'-'.$cnxFile['routes'][$r]['id'];
            $apiv= $cnxFile['apiv'];

            $tunel = [
                'url'  => 'https://'.$url.'.ngrok-free.app/'.$apiv.'/api/sse',
                'isPay'=> $cnxFile['routes'][$r]['isPay'],
                'user' => $cnxFile['routes'][$r]['user'],
                'host' => $cnxFile['routes'][$r]['host'],
            ];

            $isPriory = false;
            // Recorremos las url prioritarias para ver si la ruta a guardar lo es.
            for ($i=0; $i < $cantPriory; $i++) {
                if(mb_strpos($tunel['url'], $priorys[$i]) !== false) {
                    $isPriory = true;
                }
            }

            if(in_array($cnxFile['routes'][$r]['host'], $notUsed)) {

                if($isPriory) {
                    if($cnxFile['routes'][$r]['user'] == '5213316195698') {
                        $morePriory[] = $tunel;
                    }else{
                        array_unshift($tunnels, $tunel);
                    }
                }else{
                    $tunnels[] = $tunel;
                }

            }else{

                if($isPriory) {
                    if($cnxFile['routes'][$r]['user'] == '5213316195698') {
                        $morePriory[] = $tunel;
                    }else{
                        array_unshift($tunnelsAlt, $tunel);
                    }
                }else{
                    $tunnelsAlt[] = $tunel;
                }
            }
        }

        $cnxFile['routes'] = array_merge($morePriory, $tunnels, $tunnelsAlt);
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

        // Si el error es por que el token de whats caduco no se puede enviar el mensaje de reporte
        if(mb_strpos($error, 'caducado') !== false) {
            return;
        }

        // $subMsg = "Se intentó enviar evento de SR hacia Whatsapp";

        // $msg = "*ERROR SendToWa EN SR.*:\n\n".
        // "*Evento*: "."Por HACER"."\n".
        // "*Contacto*: ".$this->conm->to."\n".
        // "*Código*: ".$code . "\n".
        // "*Razón*: ".$error."\n".
        // "*Path*:\n\n".
        // $url."\n\n".
        // "_".$subMsg."_";

        // $this->sendText($msg, $this->reporTo);
    }

    /** */
    private function sendReporErrorBySendMy(String $method, array $headers, String $url, String $code, String $error, array $body = []) {

        $result = [
            'metodo'     => $method,
            'statusCode' => $code,
            'reason'     => $error,
            'toUrl'      => $url,
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
        // TODO
        // Esto esta detenido mientras se desarrolla
        // $this->conm = new ConmDto($this->fSys->getConmuta());
        // $this->sendText($msg, $this->reporTo);
    }

    /** Indxamos la imagen a whats */
    public function indexImage(String $pathToImage, $img): array
    {
        $this->initConmutador();
        if($this->conm != null) {
            $this->client->request(
                'POST', $this->conm->uriToWhatsapp.'/media'
            );
        }
        return [];
    }

}
