<?php

namespace App\Controller\SCP\Cotizadores;

use App\Repository\NG2ContactosRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{
	
	#[Route('scp/cotizadores/get-all-cotizadores/', methods:['get'])]
	public function getAllCotizadores(NG2ContactosRepository $contactos): Response
	{   
		$dql = $contactos->getAllCotizadores();
		return $this->json(['abort'=>false,'msg'=>'ok','body'=>$dql->getScalarResult()]);
	}

}
