<?php

namespace App\Controller\WA;

use App\Service\CommandFromWa\CmdsFromWa;
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

                $pathTo = $this->getParameter('waCmds');
                $metadata= new WaMessageDto($message);
                if( mb_strpos($metadata->body, '[cmd]') !== false ) {

                    $cmd = $metadata->extractCmdFromBody();
                    $cmd = new CmdsFromWa($cmd, $pathTo);
                    return new Response('', 200);
                }

                $filename = round(microtime(true) * 1000);
                $path  = $pathTo.'wa_'.$filename.'.json';

                $filename = 'conv_free.'.$metadata->waId.'.cnv';
                if(is_file($filename)) {

                    $metadata->campoResponsed = 'ctc_free';
                    $wh->sendMy(
                        [
                            'evento' => 'wa_message',
                            'source' => $filename,
                            'creado' => $filename,
                            'pathTo' => $path,
                            'payload'=> $metadata,
                        ],
                        $this->getParameter('getWaToken'),
                        $this->getParameter('getAnToken')
                    );
                    return new Response('', 200);
                }

                if(!is_dir($pathTo)) {
                    mkdir($pathTo);
                }

                // $pathPr= $pathTo.'pr_'.$filename.'.json';
                // file_put_contents($pathPr, json_encode($metadata->toArray()));

                $metadata->pathToBackup = $path;
                $allowPass = false;
                if($metadata->type != 'status') {

                    $r = new WaTypeResponse(
                        $metadata, $waS, $message, $pathTo,
                        $this->getParameter('waTk'),
                        $this->getParameter('nifiFld'),
                        $this->getParameter('waCots')
                    );

                    $metadata = $r->metaMsg;
                    $allowPass = $r->allowPass;
                    if($metadata->type != 'login') {
                        $isMsgOk = $r->saveMsgResult;
                        if($metadata->type != 'image') {
                            if($isMsgOk) {
                                if($r->isTest) {
                                    $isMsgOk = false;
                                }else{
                                    file_put_contents($path, $has);
                                }
                            }
                        }
                    }
                }

                if($isMsgOk || $allowPass) {
                    $wh->sendMy(
                        [
                            'evento' => 'wa_message',
                            'source' => $filename,
                            'creado' => $filename,
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

            new WaTypeResponse(
                $metadata, $waS, [], '',
                $this->getParameter('waTk'),
                $this->getParameter('nifiFld'),
                $this->getParameter('waCots')
            );

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

    /**
     * Actualizamos el token de wa desde backcore
     */
    #[Route('wa/tk/update/', methods: ['POST'])]
    public function updateWaToken(Request $req): Response
    {
        if($req->getMethod() == 'POST') {

            $field = $req->request->get('tkmy');
            if($field != '') {
                $pathTk = $this->getParameter('waTk');
                file_put_contents($pathTk, 'aldo_'.$field);
                return $this->json(['code' => 200]);
            }
        }

        return $this->json(['code' => 100]);
    }

}
