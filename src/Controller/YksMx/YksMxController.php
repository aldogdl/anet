<?php

namespace App\Controller\YksMx;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/ynk-wh')]
class YksMxController extends AbstractController
{
	#[Route('/', methods: ['get', 'post'])]
	public function indexWh(Request $req): Response
	{
	  if($req->getMethod() == 'POST' ) {
			file_put_contents('prueba_hook.json', json_encode($_POST));
		}
		return $this->json(['Yonkeros' => 'Bienvenido']);
	}

}
