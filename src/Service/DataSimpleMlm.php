<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface as ExceptionHttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface as HttpClientExceptionHttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface as ExceptionTransportExceptionInterface;

class DataSimpleMlm {

    private $params;
    private $client;
    public String $errFromMlm = '';
    public ?array $conm;
    private String $url = 'https://api.mercadolibre.com/oauth/token';

    /** */
	public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
	{
		$this->params = $container;
        $this->client = $client;
        $this->conm   = null;
	}
    
    /** Realizar Solciitud a MeLi */
    private function recoveryToken(String $codeTk, bool $isRefresh = false) : array
    {
        $code  = 401;
        $bodyResult = [];
        $this->errFromMlm = '';
        if($this->conm == null) {
            $this->getCredentialsMlm();
        }
        if($this->conm == null) {
            $this->errFromMlm = 'X Archivo de credenciales no encontrado';
            return $bodyResult;
        }
        $dataSend = [
            'grant_type' => ($isRefresh) ? 'refresh_token' : 'authorization_code',
            'client_id'  => $this->conm['appId'],
            'client_secret' => $this->conm['appTk'],
        ];
        if($isRefresh) {
            $dataSend['refresh_token'] = $this->conm['refreshTk'];
        }else {
            $dataSend['code'] = $codeTk;
            $dataSend['redirect_uri'] = 'https://autoparnet.com/mlm/code/';
        }
        $dataSend['url'] = $this->url;
        file_put_contents('w_sabee.json', json_encode($dataSend));
        try {

            $response = $this->client->request('POST', $this->url,
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => http_build_query($dataSend)
                ]
            );

            $code = $response->getStatusCode();
            if($code >= 200 && $code <= 300) {
                $code = 200;
                $bodyResult = json_decode($response->getContent(), true);
            }else {
                $this->errFromMlm = 'X ' . $response->getContent();
            }

        } catch (HttpClientExceptionHttpExceptionInterface $e) {
            // Maneja errores HTTP específicos (por ejemplo, 400, 401, 404, etc.)
            $this->errFromMlm = 'Error HTTP: ' . $e->getCode() . ' ' . $e->getMessage();
        } catch (ExceptionTransportExceptionInterface $e) {
            // Maneja errores de transporte (por ejemplo, errores de conexión, timeout, etc.)
            $this->errFromMlm = 'Error de transporte: ' . $e->getMessage();
        } catch (\Throwable $th) {
            // Maneja cualquier otro tipo de error
            $this->errFromMlm = 'Error desconocido: ' . $th->getMessage();
        }

        if($this->errFromMlm != '') {
            $bodyResult['error'] = $this->errFromMlm;
            $this->errFromMlm = '';
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
            $colabs = $result['colabs'];
            unset($result['colabs']);
            $rota = count($colabs);
            for($i = 0; $i < $rota; $i++) {
                if(!array_key_exists('roles', $colabs[$i])) {
                    continue;
                }
                if(in_array('ROLE_MAIN', $colabs[$i]['roles'])) {
                    $result = array_merge($result, $colabs[$i]);
                }
            }
            if(!array_key_exists('waId', $result)) {
                $result = array_merge($result, $colabs[0]);
            }
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
            return ['abort' => true, 'body' => ['error' => $result['error']]];
        }else if($this->errFromMlm != '') {
            return ['abort' => true, 'body' => ['error' => $this->errFromMlm]];
        }
        $saved = $this->setCodeTokenMlm($result, $slug);
        return ($saved) ? $saved : $result;
    }

    /** 
     * Refresh token
    */
    public function refreshTokenMlm(String $slug) : array
    {
        $result = $this->recoveryToken('refresh', true);
        if(array_key_exists('error', $result)) {
            return ['abort' => true, 'body' => ['error' => $result['error']]];
        }else if($this->errFromMlm != '') {
            return ['abort' => true, 'body' => ['error' => $this->errFromMlm]];
        }
        $saved = $this->setCodeTokenMlm($result, $slug);
        return ['abort' => false, 'body' => ($saved) ? $saved : $result];
    }

    /** 
     * Recuperamos las credenciales del usuario para MeLi
     * @param String $Slug
    */
    public function getCodesUser(String $slug, bool $compress = true) : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo .'/'. $slug . '.json';
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
    public function setCodeTokenMlm(array $data, String $slug) : array
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