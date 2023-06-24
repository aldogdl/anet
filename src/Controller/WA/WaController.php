<?php

namespace App\Controller\WA;

use App\Service\WA\Dom\WaExtract;
use App\Service\WA\WaService;
use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class WaController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('wa/wh/', methods: ['GET', 'POST'])]
    public function verifyWa(WebHook $wh, WaService $waS, Request $req): Response
    {
        $path  = $this->getParameter('waMessag').'pru_connection.json';
        file_put_contents($path, json_encode([
            'code' => $req->getMethod(),
            'body' => $req->getContent(),
        ]));

        if($req->getMethod() == 'GET') {

            $verify = $req->query->get('hub_verify_token');
            if($verify == $this->getParameter('getWaToken')) {
    
                $mode = $req->query->get('hub_mode');
                if($mode == 'subscribe') {
                    $challenge = $req->query->get('hub_challenge_aldo');
                    return new Response($challenge);
                }
            }
        }

        if($req->getMethod() == 'POST') {

            $filename = round(microtime(true) * 1000);
            $has = $req->getContent();
            if($has) {

                $message = json_decode($has, true);
                $motive= new WaExtract($message);

                if($motive->type != 'status') {

                    $pathTk= $this->getParameter('waTk');
                    $token = file_get_contents($pathTk);

                    $path  = $this->getParameter('waMessag').'pru_'.$filename.'.json';
                    $bytes = file_put_contents($path, $token);

                    if( mb_strpos($motive->body, '_cotizar') !== 0) {
                        $waS->hidratarAcount($message, $token);
                        $msg = 'Envia hasta 8 FOTOGRAFÍAS, primeramente.';
                        $waS->msgText('+'.$motive->waId, $msg, $motive->id);
                    }

                    $path  = $this->getParameter('waMessag').'wa_'.$filename.'.json';
                    $bytes = file_put_contents($path, $has);

                }else {
                    $bytes = 1;
                }

                $wh->sendMy(
                    [
                        'evento' => 'wa_message',
                        'source' => $filename,
                        'pathTo' => $path,
                        'payload'=> $message,
                    ],
                    $this->getParameter('getWaToken'),
                    $this->getParameter('getAnToken')
                );

                if($bytes > 0) {
                    return new Response('', 200);
                }
            }
        }

        return $this->json( [], 500 );
    }

    /**
     * Recuperamos el archivo de orden de llegada de los mensajes
     */
    #[Route('wa/get-orden-file/', methods: ['GET'])]
    public function recoveryWaOrden(WaService $waS): Response
    {
        $res = $waS->getFileOrden($this->getParameter('waSort'));
        return $this->json($res);
    }

    /**
     * Marcamos el mensaje como leido y lo eliminamos de la carpeta
     */
    #[Route('wa/mark-readed/{filename}', methods: ['GET'])]
    public function recoveryWa(Request $req, WaService $wa, String $filename): Response
    {
        if($req->getMethod() == 'GET') {
            $path = $this->getParameter('waMessag').'wa_'.$filename.'.json';
            $res = $wa->isReaded($path);
        }

        return $this->json([]);
    }

}
