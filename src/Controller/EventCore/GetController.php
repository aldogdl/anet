<?php

namespace App\Controller\EventCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Repository\NG2ContactosRepository;

class GetController extends AbstractController
{

    #[Route('event-core/get-user-by-campo/', methods:['get'])]
    public function getUserByCampo(NG2ContactosRepository $contacsEm, Request $req): Response
    {
      $campo = $req->query->get('campo');
      $valor = $req->query->get('valor');
      $user = $contacsEm->findOneBy([$campo => $valor]);
      $result = [];
      $abort = true;
      if($user) {
        $abort = false;
        $result = $contacsEm->toArray($user);
      }
      return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
    }
  
    /** */
    #[Route('event-core/conv-free/{waid}/', methods: ['GET', 'DELETE'])]
    public function putCotInConvFree(Request $req, String $waid): Response
    {
        if($req->getMethod() == 'GET') {
            $filename = 'conv_free.'.$waid.'.cnv';
            file_put_contents($filename, '');
            return $this->json(['code' => $filename]);
        }

        if($req->getMethod() == 'DELETE') {

            $filename = 'conv_free.'.$waid.'.cnv';
            if(is_file($filename)) {
                unlink($filename);
                return $this->json(['code' => 'exit']);
            }
        }

        return $this->json(['code' => 'error']);
    }
}
