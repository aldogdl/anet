<?php

namespace App\Controller\ComCore;

use App\Dtos\HeaderDto;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Repository\NG2ContactosRepository;
use App\Service\AnetShop\AnetShopSystemFileService;
use App\Service\ItemTrack\WaSender;

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
    
    /** */
    #[Route('com-core/test-com/{tokenBasic}/{fromApp}', methods:['get'])]
    public function theTestCom(WaSender $wh, String $tokenBasic, String $fromApp): Response
    {
      $tok = base64_decode($tokenBasic);
      $miTok = $this->getParameter('getAnToken');
      if($miTok == $tok) {
        $data['header'] = HeaderDto::event([], 'test_com');
        $wh->sendMy($data);
      }
      return $this->json(['abort'=> false, 'body' => 'ok']);
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
  
    /** */
    #[Route('com-core/existe-sse-not-route/{whoask}/', methods: ['GET'])]
    public function existSseNotRoute(Request $req, AnetShopSystemFileService $fSys, String $whoask): Response
    {
      $result = ['abort' => false, 'body' => 0];
      if($req->getMethod() == 'GET') {

        $path = $this->getParameter('sseNotRouteActive').'/'.$whoask;
        if(file_exists($path)) {
          $archivos = scandir($path);
          // Eliminar los elementos "." y ".." que representan la carpeta actual y la carpeta padre
          $archivos = array_diff($archivos, array('.', '..'));
          $result = ['abort' => false, 'body' => count($archivos)];
        }
      }

      return $this->json($result);
    }

}
