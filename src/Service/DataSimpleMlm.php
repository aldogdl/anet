<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DataSimpleMlm {

    private $params;
    private $client;
    public String $errFromMlm = '';
    public ?array $conm;

    /** */
	public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
	{
		$this->params = $container;
        $this->client   = $client;
	}
    
    /** Realizar Solciitud a MeLi */
    private function recoveryToken(String $codeTk) : array
    {
        $code  = 401;
        $bodyResult = [];
        $this->errFromMlm = '';
        if($this->conm == null) {
            $this->getCredentialsMlm();
        }
        if($this->conm == null) {
            $this->errFromMlm = 'Archivo de credenciales no encontrado';
            return [];
        }

        $url = 'https://api.mercadolibre.com/oauth/token';

        try {

            $response = $this->client->request('POST', $url,
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'grant_type' => 'authorization_code',
                        'client_id' => $this->conm['appId'],
                        'client_secret' => $this->conm['appTk'],
                        'code' => $codeTk,
                        'redirect_uri' => 'https://autoparnet.com/mlm/code/',
                    ]
                ]
            );

            $code = $response->getStatusCode();
            if($code >= 200 && $code <= 300) {
                $code = 200;
                $bodyResult = json_decode($response->getContent(), true);
            }else {
                $this->errFromMlm = $response->getContent();
            }

        } catch (\Throwable $th) {
            
            $this->errFromMlm = $th->getMessage();
            if($code == 401) {
                $this->errFromMlm = 'Error no manejado';
            }else if(mb_strpos($this->errFromMlm, '400') !== false) {
                $this->errFromMlm = 'Mensaje mal formado';
            }else if(mb_strpos($this->errFromMlm, 'timeout') !== false) {
                $this->errFromMlm = 'Se superó el tiempo de espera';
            }
        }
        
        if($this->errFromMlm != '') {
            $bodyResult['error'] = $this->errFromMlm;
        }

        return $bodyResult;
    }

    /** 
     * Recuperamos lod datos completos del dueño del catalogo
     * @param String $slug
    */
    public function getDataOwn(String $slug) : array
    {
        $result = [];
        
        $pathTo = $this->params->get('dtaCtc');
        if(is_dir($pathTo)) {
            $pathTo = $pathTo .'/'. $slug . '.json';
            if(is_file($pathTo)) {
                $result = json_decode(file_get_contents($pathTo), true);
                if($result) {
                    $pathTo = $this->params->get('dtaCtcLog');
                    if(is_dir($pathTo)) {
                        $pathTo = $pathTo .'/'. $slug . '.json';
                        if(is_file($pathTo)) {
                            $locks = json_decode(file_get_contents($pathTo), true);
                            if($locks) {
                                $result['locks'] = $locks;
                            }
                        }
                    }
                }
            }
        }

        if(array_key_exists('colabs', $result)) {
            unset($result['colabs']);
        }
        return $result;
    }

    /** 
     * Intercambiamos el codigo por token
    */
    public function parseCodeToToken(String $code, String $slug) : array
    {
        $result = $this->recoveryToken($code);
        if(array_key_exists('error', $result)) {
            $result = ['abort' => true, 'body' => $result['error']];
        }
        $saved = $this->setCodeTokenMlm($result, $slug);
        return ($saved) ? $saved : $result;
    }

    /** 
     * Recuperamos las credenciales del usuario para MeLi
     * @param String $waId
    */
    public function getCodesUser(String $waId, bool $compress = true) : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo .'/'. $waId . '.json';
        if(is_file($pathTo)) {

            $res = '';
            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                if(!$compress) { return $data; }
                $res = json_encode($data);
                return ['codes' => base64_encode($res)];
            }
        }
        return [];
    }
	
    /** */
    public function getDataLoksUserTest() : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo .'/test_mlm.json';
        if(is_file($pathTo)) {
            $data = file_get_contents($pathTo);
            if($data) {
                return ['deco' => base64_encode($data)];
            }
        }
        return [];
    }

    /** */
    public function setUserTest(array $newDt): void
    {
        $data = [];
        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        
        $pathTo = $pathTo .'/test_mlm.json';
        if(is_file($pathTo)) {
            file_put_contents($pathTo, json_encode($newDt));
        }
    }
    
    /** 
     * Guardamos los datos del token de la usuario MLM
     * @param array $data
     * @param String $waId
    */
    private function setCodeTokenMlm(array $data, String $slug) : array
    {
        $result = [];
        
        if(array_key_exists('access_token', $data)) {

            $pathTo = $this->params->get('dtaCtcLog');
            if(!is_dir($pathTo)) { mkdir($pathTo); }

            $pathTo = $pathTo .'/'. $slug . '.json';
            $result = [
                'token'  => $data['access_token'],
                'userId' => $data['user_id'],
                'expire' => $data['expires_in'],
                'scope'  => $data['scope'],
                'refreshTk' => $data['refresh_token'],
                'updatedAt' => (integer) microtime(true) * 1000,
            ];
            file_put_contents($pathTo, json_encode($result));
        }
        
        return $result;
    }

    /**
     * Recuperamos las credenciales de app
     */
    private function getCredentialsMlm(): void
    {
        $this->conm = null;
        $pathTo = $this->params->get('anyMlm');
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                $appId = str_replace('edi:', '', $data['edi']);
                $appTk = str_replace('yek:', '', $data['yek']);
                $this->conm = ['appId' => $appId, 'appTk' => $appTk];
            }
        }
    }
    
}