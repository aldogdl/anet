<?php

namespace App\Controller\SCP\Cotizadores;

use App\Repository\FiltrosRepository;
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

	/**
	 * REcuperamos los filtros de un determinado cotizador
	 */
  #[Route('scp/cotizadores/get-filtro-by-emp/{emp}/', methods:['get'])]
  public function getFiltroByEmp(FiltrosRepository $filtEm, int $emp): Response
  {
    $result = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $dql = $filtEm->getFiltroByEmp($emp);
	$result['body'] = $dql->getScalarResult();
    return $this->json($result);
  }

	/**
	 * REcuperamos los filtros de un determinado cotizador
	 */
  #[Route('scp/cotizadores/del-filtro-by-id/{id}/', methods:['get'])]
  public function delFiltroById(FiltrosRepository $filtEm, int $id): Response
  {
    return $this->json($filtEm->delFiltroById($id));
  }

}
