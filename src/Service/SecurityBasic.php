<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SecurityBasic
{

	private ParameterBagInterface $params;
	function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
	}

	/** */
	public function isValid(String $token): bool
	{
		if(mb_strpos($token, 'any') !== false) {
			$recibido = $token;
		} else {
			$recibido = base64_decode($token);
		}
		$llave = $this->params->get('anyToken');
		if($recibido == $llave) {
			return true;
		}
		return false;
	}

}
