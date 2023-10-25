<?php

namespace App\Controller\Mlm;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\WA\Dom\WaMessageDto;
use App\Service\WA\WaService;
use App\Service\WapiRequest\ProcesarMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MlmController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/wh/notifications/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req, ProcesarMessage $processMsg): Response
    {

        if($req->getMethod() == 'GET') {
            $verify = $req->query->get('code_challenge');
            if($verify == $this->getParameter('getShopCTk')) {
                return new Response('listo MLM');
            }
        }

        if($req->getMethod() == 'POST') {
            
            $has = $req->getContent();
            if(strlen($has) < 50) {
                return $this->json( [], 500 );
            }
            
            $message = json_decode($has, true);
            $processMsg->execute($message);
            return new Response('', 200);
        }
    }

    /** */
    #[Route('shop/mlm/', methods: ['get'])]
    public function anulandoRoute(): RedirectResponse | Response
    {
  
    //   if($slug == '') {
    //       return $this->json(['hola' => 'Bienvenido...']);
    //   }
      return $this->redirect('https://www.autoparnet.com/shop/?emp=iksan', 301);
    }
  
}
