<?php

namespace App\Controller\Cotiza;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;

class GetController extends AbstractController
{

  #[Route('cotiza/get-all-marcas/', methods:['get'])]
  public function getAllMarcas(AO1MarcasRepository $marcasEm): Response
  {
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $marcasEm->getAllAsArray()
    ]);
  }

  #[Route('cotiza/get-modelos-by-marca/{idMarca}/', methods:['get'])]
  public function getModelosByMarca(AO2ModelosRepository $modsEm, $idMarca): Response
  {
    $dql = $modsEm->getAllModelosByIdMarca($idMarca);
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $dql->getScalarResult()
    ]);
  }

  #[Route('api/cotiza/get-user-by-campo/', methods:['get'])]
  public function getUserByCampo(NG2ContactosRepository $contacsEm, Request $req): Response
  {
    $campo = $req->query->get('campo');
    $valor = $req->query->get('valor');
    $user = $contacsEm->findOneBy([$campo => $valor]);
    $result = [];
    $abort = true;
    if($user) {
      $abort = false;
      $result['u_id'] = $user->getId();
      $result['u_roles'] = $user->getRoles();
    }
    return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
  }

  #[Route('api/cotiza/is-tokenapz-caducado/', methods:['get'])]
  public function isTokenApzCaducado(): Response
  {
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
  }

}
