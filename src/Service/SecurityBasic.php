<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SecurityBasic
{

    private $params;
    function __construct(ParameterBagInterface $container)
    {
        $this->params = $container;
    }

    /** */
    public function isValid(String $token): bool
    {
        $recibido = base64_decode($token);
        $llave = $this->params->get('getShopCTk');
        if($recibido == $llave) {
            return true;
        }
        return false;
    }

    /** 
     * Recuperamos los datos de conexion para la app de AnetCraw
    */
    public function getDtCnx(): array
    {
        $vers = $this->params->get('verapps');
        $llave = $this->params->get('anetCnx');
        $partes = explode(':', $llave);
        $version = file_get_contents($vers);
        $verAppShop = '';
        $verAppCraw = '';
        if($version != '') {
            $version = json_decode($version, true);
            $verAppShop (array_key_exists('anet_shop', $version)) ? $version['anet_shop'] : '';
            $verAppCraw (array_key_exists('anet_craw', $version)) ? $version['anet_craw'] : '';
        }
        return [
            'u' => $partes[0], 'p' => $partes[1],
            'craw' => $verAppCraw, 'shop' => $verAppShop
        ];
    }

}
