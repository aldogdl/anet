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
    #[Route('mlm/code/', methods: ['GET'])]
    public function verifyMlm(Request $req): Response
    {
        $state = $req->query->get('state');
        if(mb_strpos($state, ':') !== false) {
            
            $code = $req->query->get('code');
            if(mb_strlen($code) > 10) {
                $partes = explode(':', $state);
                $action = $partes[0];
                $slug = $partes[1];
                file_put_contents('mlm_'.$slug.'.json', json_encode([
                    'code' => $code, 'action' => $action, 'slug' => $slug
                ]));
                $this->redirect('https://www.autoparnet.com/shop/mlm-bind?state='.$state, 301);
            }
        }
        return new Response(200);
    }

    /**
     * Endpoint para la recuperar la conx
     */
    #[Route('mlm/get-codes/', methods: ['GET'])]
    public function mlmGetCodes(Request $req): Response
    {
        if($req->getMethod() == 'GET') {
            $theGet = $this->getParameter('anetMlm');
            $data = json_decode(file_get_contents($theGet), true);
            return $this->json($data);
        }
        return new Response(500);
    }

    /**
     * 
     */
    #[Route('mlm/get-code-auth/', methods: ['GET'])]
    public function mlmGetCodeAuth(Request $req): Response
    {
        if($req->getMethod() == 'GET') {
            $theGet = $this->getParameter('anetMlm');
            $data = json_decode(file_get_contents($theGet), true);
            return $this->json($data);
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

}
