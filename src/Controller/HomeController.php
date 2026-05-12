<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class HomeController extends AbstractController
{
	#[Route('/', methods: ['get'])]
	public function index(): Response
	{
		return $this->json(['hola' => 'Bienvenido', 'en que podemos atenderte?']);
	}

	#[Route('/qr', methods: ['get'])]
	public function qr(string $data, int $size = 300): Response
	{
		$qrCode = QrCode::create($data);
		$writer = new PngWriter();
		$result = $writer->write($qrCode);

		return new Response($result->getString(), 200, [
			'Content-Type' => 'image/png',
		]);
	}
}
