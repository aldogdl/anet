<?php

namespace App\Controller\YksMx;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/ynk-wh')]
class YksMxController extends AbstractController
{
	#[Route('/', methods: ['get'])]
	public function indexWh(): Response
	{
		return $this->json(['Yonkeros' => 'Bienvenido']);
	}

}
