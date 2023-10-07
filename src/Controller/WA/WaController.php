<?php

namespace App\Controller\WA;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\WA\Dom\WaMessageDto;
use App\Service\WA\WaService;
use App\Service\WapiRequest\ProcesarMessage;

class WaController extends AbstractController
{
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
            file_put_contents('message.json', json_encode($message));
            $processMsg->execute($message);
            return new Response('', 200);
        }
    }

    /**
     * Colocamos una marca para saber que un cotizador tiene una
     * conversaion libre con anet
     */
    #[Route('wa/put-cot-in-conv-free/', methods: ['POST', 'DELETE'])]
    public function putCotInConvFree(Request $req, WaService $waS): Response
    {
        $data = $req->toArray();
        if($data['change'] != 'anetConvFree') {
            return $this->json(['code' => 'error']);
        }

        if($req->getMethod() == 'POST') {
            $filename = 'conv_free.'.$data['waid'].'.cnv';
            file_put_contents($filename, '');
            return $this->json(['code' => $filename]);
        }

        if($req->getMethod() == 'DELETE') {

            if(!is_file($data['file'])) {
                return $this->json(['code' => 'file']);
            }

            unlink($data['file']);
            $metadata = new WaMessageDto([]);
            $waid = str_replace('conv_free.', '', $data['file']);
            $waid = str_replace('.cnv', '', $waid);

            $metadata->extractPhoneFromWaId($waid);
            $metadata->type = 'close_free';


            return $this->json(['code' => $data['file']]);
        }

        return $this->json(['code' => 'error']);
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


}
