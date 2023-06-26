<?php

namespace App\Controller\WA;

use App\Service\WA\WaTypeResponse;
use App\Service\WA\Dom\WaMessageDto;
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
            if($has) {

                $isMsgOk = true;
                $message = json_decode($has, true);
                $metadata= new WaMessageDto($message);

                $pathTo = $this->getParameter('waMessag');
                if(!is_dir($pathTo)) {
                    mkdir($pathTo);
                }

                $filename = round(microtime(true) * 1000);
                $path  = $pathTo.'/wa_'.$filename.'.json';
                $pathPr= $pathTo.'/pr_'.$filename.'.json';
                file_put_contents($pathPr, json_encode($metadata->toArray()));

                $metadata->pathToBackup = $path;

                if($metadata->type != 'status') {

                    $r = new WaTypeResponse(
                        $metadata, $waS, $message, $pathTo, $this->getParameter('waTk'),
                        $this->getParameter('nifiFld')
                    );

                    $metadata = $r->metaMsg;
                    if($metadata->type != 'login') {
                        $isMsgOk  = $r->saveMsgResult;
                        if($isMsgOk) {
                            file_put_contents($path, $has);
                        }
                    }
                }

                if($isMsgOk) {
                    // 'payload'=> $message,
                    $wh->sendMy(
                        [
                            'evento' => 'wa_message',
                            'source' => $filename,
                            'pathTo' => $path,
                            'payload'=> $metadata,
                        ],
                        $this->getParameter('getWaToken'),
                        $this->getParameter('getAnToken')
                    );
                }

                return new Response('', 200);
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
