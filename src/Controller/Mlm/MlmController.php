<?php

namespace App\Controller\Mlm;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\RedirectResponse;

class MlmController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req): Response
    {
        if($req->getMethod() == 'GET') {
            return new Response();
        }
        return new Response(500);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/notifications/', methods: ['GET', 'POST'])]
    public function notisMlm(): Response
    {
        return new Response('listo MLM');
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
