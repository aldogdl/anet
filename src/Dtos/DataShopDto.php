<?php

namespace App\Dtos;

use App\Repository\UsComRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataShopDto {

    private UsComRepository $em;
    private ParameterBagInterface $params;
    // El resultados
    private array $user = [];
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
        $this->user = [
            'iku'  => $data['iku'],
            'slug' => $data['slug'],
            'dev'  => $data['dev'],
            'tkfb' => $data['tkfb'],
        ];

        // Primero obtenemos los Datos básicos de la empresa
        $this->dataOwnApp();
        if($this->error != '') {
            return $this->returnErr();
        }

        // Recuperamos los puentes registrados
        try {
            $this->user['ng'] = json_decode(
                file_get_contents($this->params->get('ngile')), true
            );
        } catch (\Throwable $th) {
            $this->error = $th->getMessage();
        }

        if($this->error == '') {
            try {
                $code = json_decode(
                    file_get_contents($this->params->get('tkwaconm')), true
                );
                $this->user['conm'] = $code[$code['modo']];
            } catch (\Throwable $th) {
                $this->error = $th->getMessage();
            }
        }

        if($this->error == '') {
            $this->user['fwb'] = $this->params->get('certWebFb');
        }

        if($this->error == '') {
            try {
                $data = json_decode(
                    file_get_contents(
                        $this->params->get('dtaCtcLog').'/'. $this->user['slug'] . '.json'
                    ), true
                );
                $this->user['dataml'] = [
                    'uid' => $data['userId'],
                    'exp' => $data['expire'],
                    'ref' => $data['refreshTk'],
                    'tk'  => $data['token'],
                    'uAt' => $data['updatedAt'],
                ];
            } catch (\Throwable $th) {
                $this->error = $th->getMessage();
            }
        }

        $this->em->updateOnlyToken($this->user['tkfb'], $this->user['iku']);

        if($this->error == '') {
            return ['abort' => false, 'body' => $this->cifrar($this->user)];
        }
        return ['abort' => true, 'body' => ['X '.$this->error]];
    }

    /** Datos de shop from file */
    private function dataOwnApp(): void
    {
        $this->error = '';
        try {
            $data = json_decode(
                file_get_contents($this->params->get('dtaCtc').'/'.$this->user['slug'].'.json'),
                true
            );
        } catch (\Throwable $th) {
            $this->error = 'X No existe la empresa '.$this->user['slug'];
        }

        if($this->error != '') {
            return;
        }

        $this->user['slug'] = $data['slug'];
        $this->user['logo'] = $data['logo'];
        $this->user['empresa'] = $data['empresa'];
        $this->user['address'] = $data['address'];
        $this->user['colonia'] = $data['colonia'];
        $this->user['localidad'] = $data['localidad'];
        $this->user['prestige'] = $data['prestige'];

        $colabs = [];
        $colabMain = [];
        $rota = count($data['colabs']);

        for ($i=0; $i < $rota; $i++) {
            
            $passEncript = $this->cifrar($data['colabs'][$i]['pass']);
            if(in_array('ROLE_MAIN', $data['colabs'][$i]['roles'])) {
                $colabMain = $data['colabs'][$i];
                $colabMain['pass'] = $passEncript;
                $getter = true;
                continue;
            }

            $data['colabs'][$i]['pass'] = $passEncript;
            $colabs[] = $data['colabs'][$i];
        }

        if($colabMain) {
            // [NOTA] El IKU del usuario lo tomamos al momento de recuperar
            // sus datos en la tabla de UsCom mas adelante
            $this->user['waId']  = $colabMain['waId'];
            $this->user['pass']  = $colabMain['pass'];
            $this->user['roles'] = $colabMain['roles'];
            $this->user['login'] = $colabMain['login'];
            $this->user['kduk']  = $colabMain['kduk'];
            $this->user['stt']   = $colabMain['stt'];
            $this->user['contacto'] = $colabMain['nombre'].' '.$colabMain['fullName'];
        }

        $this->user['colabs'] = $colabs;
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