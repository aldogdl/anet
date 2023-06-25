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

            $filename = round(microtime(true) * 1000);
            $pathTo = $this->getParameter('waMessag');
            $path  = $pathTo.'/wa_'.$filename.'.json';
            if(!is_dir($pathTo)) {
                mkdir($pathTo);
            }

            $has = $req->getContent();
            if($has) {

                $message = json_decode($has, true);
                $motive= new WaExtract($message);

                if($motive->type != 'status') {

                    $pathTk = $this->getParameter('waTk');
                    $token  = file_get_contents($pathTk);
                    // _cotizar
                    if( mb_strpos($motive->body, 'continuar' ) !== false) {

                        $waS->hidratarAcount($message, $token);
                        $msg = '😃👍 Gracias!!.. \n Envia *FOTOGRAFÍAS* por favor.';
                        $result = $waS->msgText('+'.$motive->waId, $msg, $motive->id);
                        file_put_contents('file_image_'.$motive->waId, '');

                        if(count($result) > 0) {
                            file_put_contents(
                                $pathTo.'/fails_'.$filename.'.json',
                                json_encode([
                                    'razon'  => 'Mensaje no se pudo enviar a WhatsApp',
                                    'body'   => $result
                                ])
                            );
                        }
                    }

                    if($motive->type == 'image') {
                        
                        if(is_file('file_image_'.$motive->waId)) {

                            unlink('file_image_'.$motive->waId);

                            $waS->hidratarAcount($message, $token);
                            // $msg = 'Ok!!👌🏼\\n'.
                            // 'DETALLES de la Pieza.';
                            $msg = '👌🏼 Ok!! ahora los *DETALLES* de la Pieza.';
                            $result = $waS->msgText('+'.$motive->waId, $msg, $motive->id);
                            if(count($result) > 0) {
                                file_put_contents(
                                    $pathTo.'/fails_'.$filename.'.json',
                                    json_encode([
                                        'razon'  => 'Mensaje no se pudo enviar a WhatsApp',
                                        'body'   => $result
                                    ])
                                );
                            }
                        }
                    }

                    if($motive->type == 'text') {
                        
                        $waS->hidratarAcount($message, $token);
                        $msg = '👌🏼 Muy bien!! Tú mejor *COSTO* cuál sería?.';
                        $result = $waS->msgText('+'.$motive->waId, $msg, $motive->id);
                        if(count($result) > 0) {
                            file_put_contents(
                                $pathTo.'/fails_'.$filename.'.json',
                                json_encode([
                                    'razon'  => 'Mensaje no se pudo enviar a WhatsApp',
                                    'body'   => $result
                                ])
                            );
                        }
                    }

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
