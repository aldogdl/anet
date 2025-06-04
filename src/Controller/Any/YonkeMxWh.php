<?php

namespace App\Controller\Any;

use App\Service\Any\dto\MsgWs;
use App\Service\Pushes;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/yonke-mx')]
class YonkeMxWh extends AbstractController
{
    /**
     * Obtenemos el request contenido decodificado como array
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function toArray(Request $req, String $campo): array
    {
        $content = $req->request->get($campo);
        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException(sprintf('No se puede decodificar el body, "%s".', get_debug_type($content)));
        }

        if (!\is_array($content)) {
            throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
        }
        return $content;
    }

    /** */
    #[Route('/wh', methods: ['get', 'post'])]
    public function webhookWa(Request $req): Response
    {
        if( $req->getMethod() == 'POST' ) {

            $msg = new MsgWs(json_decode($req->getContent(), true));
            if($msg->type == 'stt') { return new Response(200); }
            
            file_put_contents('wa_post_'.uniqid().'.json', $msg->toJson());

        } elseif( $req->getMethod() == 'GET' ) {

            $verify = $req->query->get('hub_verify_token');
            if($verify == 'any2536_1975&appws') {
    
                $mode = $req->query->get('hub_mode');
                if($mode == 'subscribe') {
                    $challenge = $req->query->get('hub_challenge');
                    file_put_contents('de_wa_get.json', json_encode([
                        'mode' => $mode, 
                        'verify' => $verify, 
                        'challenge' => $challenge, 
                    ]));
                    return new Response($challenge);
                }
            }
        }
        return new Response(400);
    }

}
