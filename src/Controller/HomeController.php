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
	public function qr(\Symfony\Component\HttpFoundation\Request $request): Response
	{
		$data = $request->query->get('data', '');
		$size = $request->query->getInt('size', 300);

		// Si los datos vienen en Base64, los decodificamos
		// Comprobamos si es un base64 válido
		if (base64_encode(base64_decode($data, true)) === $data) {
			$data = base64_decode($data);
		}

		$qrCode = QrCode::create($data)
			->setSize($size)
			->setMargin(10);

		$writer = new PngWriter();
		$result = $writer->write($qrCode);

		return new Response($result->getString(), 200, [
			'Content-Type' => 'image/png',
		]);
	}
}
