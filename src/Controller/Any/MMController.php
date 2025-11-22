<?php

namespace App\Controller\Any;

use App\Repository\MMEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/any-mm')]
class MMController extends AbstractController
{

	/** */
	#[Route('/elements', methods: ['get', 'post', 'delete'])]
	public function mmElements(Request $req, MMEntityRepository $em): Response
	{
		if( $req->getMethod() == 'POST' ) {
			$data = $req->getContent();
			if($data) {
					return $this->json($em->setMM( json_decode($data, true) ));
			}
		} elseif( $req->getMethod() == 'GET' ) {
			$idMrk = $req->query->get('idMrk');
			return $this->json($em->getMM( $idMrk ));
		}
		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

	/** */
	#[Route('/mm-slim', methods: ['get'])]
	public function mmGetModelsSlim(Request $req, MMEntityRepository $em): Response
	{
		if( $req->getMethod() == 'GET' ) {
			$tipo = $req->query->get('tipo');
			return $this->json($em->getMMSlim( $tipo ));
		}
		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

}
