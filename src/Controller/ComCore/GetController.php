<?php

namespace App\Controller\ComCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Repository\NG2ContactosRepository;
use App\Service\AnetShop\AnetShopSystemFileService;
use App\Service\AnetTrack\WaSender;

class GetController extends AbstractController
{

    #[Route('com-core/get-user-by-campo/', methods:['get'])]
    public function getUserByCampo(Request $req, NG2ContactosRepository $contacsEm): Response
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
  
    #[Route('com-core/test-com/{tokenBasic}/{fromApp}', methods:['get'])]
    public function theTestCom(WaSender $wh, String $tokenBasic, String $fromApp): Response
    {
      $response = ['evento' => '', 'body' => ''];
      
      $tok = base64_decode($tokenBasic);
      $miTok = $this->getParameter('getAnToken');
      if($miTok == $tok) {
        $evento = ($fromApp == 'anet_track') ? 'whatsapp_api' : 'anet_shop';
        $wh->sendMy([
          'evento' => $evento,
          'subEvent' => 'test_com'
        ]);
      }
      return $this->json($response);
    }
  
    /** */
    #[Route('com-core/the-solicitante/', methods: ['GET'])]
    public function theSolicitante(Request $req, AnetShopSystemFileService $fSys): Response
    {
        $result = ['abort' => true, 'body' => 'Error Inesperado'];
        if($req->getMethod() == 'GET') {
            $solz = $fSys->getAllSolicitantes();
            $result = ['abort' => false, 'msg' => 'Results: '.count($solz), 'body' => $solz];
        }

        return $this->json($result);
    }

}
