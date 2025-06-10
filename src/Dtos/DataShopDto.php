<?php

namespace App\Dtos;

use App\Entity\UsCom;
use App\Repository\UsComRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataShopDto {

    private UsComRepository $em;
    private ParameterBagInterface $params;
    // El resultados
    private array $user = [];
    // Slug dueño de la app
    private String $slug = '';
    // Tipo de dispositivo
    private String $dev = '';
    private $metodo = 'AES-256-CBC';
    private $clave = '25361975';
    private String $error = '';

    /** */
    public function __construct(UsComRepository $em, ParameterBagInterface $params)
    {
        $this->em = $em;
        $this->params = $params;
        $this->clave = $this->params->get('getAnToken');
    }

    /** */
    public function getSimpleData(array $data) : array
    {
        $this->slug = $data['slug'];
        $this->dev = $data['dev'];

        // Datos de la empresa
        $this->dataOwnApp();
        if($this->error != '') {
            return $this->returnErr();
        }
        try {
            $this->user['ng'] = json_decode(
                file_get_contents(
                    $this->params->get('ngile')
                ), true
            );
        } catch (\Throwable $th) {}

        return ['abort' => false, 'body' => $this->user];
    }

    /** */
    public function getMetaBussiness(UsCom $usCom) : array
    {
        $this->slug = $usCom->getOwnApp();
        $this->dev = $usCom->getDev();
        // Datos de ml
        $this->dataOwnMl();
        if($this->error != '') {
            return $this->returnErr();
        }

        return $this->prepareMetaData($usCom);
    }

    /** */
    public function getMetaCustomer(UsCom $usCom) : array
    {
        $this->slug = $usCom->getOwnApp();
        $this->dev = $usCom->getDev();
        return $this->prepareMetaData($usCom);
    }

    /** */
    private function prepareMetaData(UsCom $usCom) : array
    {
        // Datos de comunicacion del dueño
        $this->user['iku']  = $usCom->getIku();
        $this->user['tkfb'] = $usCom->getTkfb();
        $this->user['stt']  = $usCom->getStt();
        $this->user['login']= $usCom->getStt();

        // Datos del conmutador
        $this->dataConmutador();
        if($this->error != '') {
            return $this->returnErr();
        }
        $this->user['fwb'] = $this->params->get('certWebFb');
        dd($this->user);
        return ['abort' => false, 'body' => $this->cifrar($this->user)];
    }

    /** Datos de shop from file */
    private function dataOwnApp(): void
    {
        $this->error = '';
        try {

            $data = json_decode(
                file_get_contents($this->params->get('dtaCtc').'/'.$this->slug.'.json'),
                true
            );
            $this->user['slug'] = $data['slug'];
            $this->user['logo'] = $data['logo'];
            $this->user['empresa'] = $data['empresa'];
            $this->user['address'] = $data['address'];
            $this->user['colonia'] = $data['colonia'];
            $this->user['localidad'] = $data['localidad'];
            $this->user['prestige'] = $data['prestige'];

            $rota = count($data['colabs']);
            $colabs = [];
            for ($i=0; $i < $rota; $i++) {
                $pass = $this->cifrar($data['colabs'][$i]['pass']);
                if(in_array('ROLE_MAIN', $data['colabs'][$i]['roles'])) {
                    // [NOTA] El IKU del usuario lo tomamos al momento de recuperar
                    // sus datos en la tabla de UsCom mas adelante
                    $this->user['waId']  = $data['colabs'][$i]['waId'];
                    $this->user['pass']  = $pass;
                    $this->user['roles'] = $data['colabs'][$i]['roles'];
                    $this->user['login'] = $data['colabs'][$i]['login'];
                    $this->user['kduk']  = $data['colabs'][$i]['kduk'];
                    $this->user['stt']   = $data['colabs'][$i]['stt'];
                    $this->user['contacto'] = $data['colabs'][$i]['nombre'].' '.$data['colabs'][$i]['fullName'];
                }else{
                    $data['colabs'][$i]['pass'] = $pass;
                    $colabs[] = $data['colabs'][$i];
                }
            }

            $this->user['colabs'] = $colabs;
            $this->dataComAndColabs();

        } catch (\Throwable $th) {
            $this->error = 'X No existe la empresa '.$this->slug;
        }
    }

    /** */
    private function dataOwnMl(): void
    {
        try {
            $data = json_decode(
                file_get_contents(
                    $this->params->get('dtaCtcLog').'/'.$this->slug.'.json'
                ), true
            );
        } catch (\Throwable $th) {}
        
        $this->user['dataml'] = [
            'uid' => $data['userId'],
            'exp' => $data['expire'],
            'ref' => $data['refreshTk'],
            'tk'  => $data['token'],
            'uAt' => $data['updatedAt'],
        ];
    }

    /** */
    private function dataComAndColabs(): void
    {
        $usersWaIds = [$this->user['waId']];
        $rota = count($this->user['colabs']);
        for ($i=0; $i < $rota; $i++) { 
            $usersWaIds[] = $this->user['colabs'][$i]['waId'];
        }
        $data = $this->em->getDataComColabs($this->slug, $usersWaIds, $this->dev);

        // Primero actualizamos los datos del role principal
        if(array_key_exists($this->user['waId'], $data)) {

            $this->user['iku'] = $data[$this->user['waId']]['iku'];
            $this->user['tkfb'] = $data[$this->user['waId']]['tk'];
            $this->user['stt'] = $data[$this->user['waId']]['stt'];
            $this->user['login'] = $data[$this->user['waId']]['stt'];
            $fechaLimite = (new \DateTimeImmutable())->sub(new \DateInterval('PT23H55M'));
            if($data[$this->user['waId']]['lastAt'] < $fechaLimite) {
                // Han pasado más de 23h 55m desde la fecha
                $this->user['stt'] = 0;
                $this->user['login'] = 0;
            }
        }

        // Proceguimos con los colaboradores
        for ($i=0; $i < $rota; $i++) {
            
            $key = $this->user['colabs'][$i]['waId'];
            if(array_key_exists($key, $data)) {
                $this->user['colabs'][$i]['iku'] = $data[$key]['iku'];
                $this->user['colabs'][$i]['tkfb'] = $data[$key]['tkfb'];
                $this->user['colabs'][$i]['stt'] = $data[$key]['stt'];
                $this->user['colabs'][$i]['login'] = $data[$key]['stt'];
                $fechaLimite = (new \DateTimeImmutable())->sub(new \DateInterval('PT23H55M'));
                if($data[$key]['lastAt'] < $fechaLimite) {
                    $this->user['colabs'][$i]['stt'] = 0;
                    $this->user['colabs'][$i]['login'] = 0;
                }
            }
        }
    }

    /** */
    private function dataConmutador(): void
    {
        try {
            
            $code = json_decode(file_get_contents($this->params->get('tkwaconm')), true);
        } catch (\Throwable $th) {}
        
        $this->user['conm'] = $code[$code['modo']];
    }

    // Función para cifrar datos
    private function cifrar($data) {

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->metodo));
        $key = hash('sha256', $this->clave, true);
        $cifrado = openssl_encrypt(json_encode($data), $this->metodo, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cifrado);
    }

    // Función para descifrar datos
    private function descifrar($base64) {
        $datos = base64_decode($base64);
        $iv_len = openssl_cipher_iv_length($this->metodo);
        $iv = substr($datos, 0, $iv_len);
        $cifrado = substr($datos, $iv_len);
        return openssl_decrypt($cifrado, $this->metodo, $this->clave, OPENSSL_RAW_DATA, $iv);
    }

    /** */
    private function returnErr(): array {
        return ['abort' => true, 'body' => $this->error];
    }
}