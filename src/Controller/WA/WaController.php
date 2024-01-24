<?php

namespace App\Controller\WA;

use App\Service\WapiProcess\DecodeTemplate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\WapiProcess\ProcesarMessage;

class WaController extends AbstractController
{
    /**
     * 
     */
    #[Route('wa/wh/test/', methods: ['GET'])]
    public function whatsTest(Request $req, ProcesarMessage $processMsg): Response
    {
        if($req->getMethod() == 'GET') {

            $message = json_decode(file_get_contents('tracking/message.json'), true);
            $processMsg->execute($message, true);
            // $deco = new DecodeTemplate([]);
            // $final = $deco->decode($message);
            
        }
        return new Response('ok', 200);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('wa/wh/', methods: ['GET', 'POST'])]
    public function verifyWa(Request $req, ProcesarMessage $processMsg): Response
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
            $processMsg->execute($message);
            return new Response('', 200);
        }
    }

}
