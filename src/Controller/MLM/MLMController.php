<?php

namespace App\Controller\MLM;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MLMController extends AbstractController
{
    /**
     * Endpoint para la verificacion de mercado libre
     */
    #[Route('mlm/bienvenido/', methods: ['GET'])]
    public function mlmWelcome(Request $req): Response
    {

        if($req->getMethod() == 'GET') {
            $challenge = ['mlm' => 'Bienvenido'];
            return $this->json($challenge);
        }

        return $this->json( [], 500 );
    }

    /**
     * Endpoint para recibir notificaciones
     */
    #[Route('mlm/notifications/', methods: ['POST'])]
    public function mlmNotificaciones(Request $req): Response
    {

        if($req->getMethod() == 'POST') {
            
            $has = $req->getContent();
            if($has) {

                $filename = round(microtime(true) * 1000);
                $path = 'mlm_'.$filename.'.json';
                file_put_contents($path, $has);

                return new Response('', 200);
            }
        }

        return $this->json( [], 500 );
    }

}
