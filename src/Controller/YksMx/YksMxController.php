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
			$data = $req->getContent();
			if($data) {
				$data = json_decode($data, true);
				if(isset($data['event']) && $data['event'] == 'product.restored') {
					$data['ok'] = 'WH Recibido';
				} else if(isset($data['event']) && $data['event'] == 'product.deleted') {
					$data['ok'] = 'WH Recibido';
				} else {
					$data['ok'] = 'WH Recibido';
					$data['event_failed'] = 'Evento no reconocido';
				}
			}
			file_put_contents('prueba_hook.json', json_encode($data));
		}
		return $this->json(['Yonkeros' => 'Bienvenido']);
	}

}
