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
}