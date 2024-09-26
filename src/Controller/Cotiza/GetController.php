<?php

namespace App\Controller\Cotiza;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;

class GetController extends AbstractController
{

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
