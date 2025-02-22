<?php

namespace App\Controller\WA;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Service\WaConsumer;

class WaController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('wa/wh/{test}', methods: ['GET', 'POST'])]
    public function verifyWa(Request $req, WaConsumer $consumer, String $test = ''): Response
    {
        if($req->getMethod() == 'GET') {

            $verify = $req->query->get('hub_verify_token');
            if($verify == $this->getParameter('getWaToken')) {
    
                $mode = $req->query->get('hub_mode');
                if($mode == 'subscribe') {
                    $challenge = $req->query->get('hub_challenge');
                    return new Response($challenge);
                }
            }
        }

        if($req->getMethod() == 'POST') {
            
            $has = $req->getContent();
            if(strlen($has) < 50) {
                return $this->json( [], 500 );
            }
            
            $message = json_decode($has, true);
            $consumer->exe($message, ($test == '') ? false : true);
            return new Response('', 200);
        }
    }

}
