<?php

namespace App\Controller\WA;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\WapiProcess\ProcesarMessage;
use App\Service\AnetTrack\WaConsumer;

class WaController extends AbstractController
{
    /**
     * 
     */
    #[Route('wa/wh/test/', methods: ['GET'])]
    public function whatsTest(Request $req, ProcesarMessage $processMsg): Response
    {
        if($req->getMethod() == 'GET') {

            $time = '2024-02-03 14:07:43';
            $echo = strtotime($time);
            dump($echo);
            $time1 = date('Y-m-d G:i:s');
            dump($time1);
            dd(time() - $echo);
            
            // $message = json_decode(file_get_contents('tracking/message.json'), true);
            // $processMsg->execute($message, true);
            // $deco = new DecodeTemplate([]);
            // $final = $deco->decode($message);
            
        }
        return new Response('ok', 200);
    }

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

    /**
     * Endpoint para la verificacion de conección
     * Entrada anterior se esta probando la nueva
     */
    #[Route('wa/wh-temp/', methods: ['GET', 'POST'])]
    public function verifyWaTmp(Request $req, ProcesarMessage $processMsg): Response
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
