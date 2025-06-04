<?php

namespace App\Controller\Any;

use App\Entity\UsCom;
use App\Repository\UsComRepository;
use App\Service\Pushes;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/sys-com')]
class SysCom extends AbstractController
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
    #[Route('/update-tkfb', methods: ['post'])]
    public function updateTkFb(Request $req, UsComRepository $em): Response
    {
        if( $req->getMethod() == 'POST' ) {

            $obj = new UsCom();
            $obj->fromJson(json_decode($req->getContent(), true));
            $em->updateTkFb($obj);
        }
        return new Response(400);
    }

    /** */
    #[Route('/push-core', methods: ['post'])]
    public function sendPushToCore(Request $req, Pushes $push): Response 
    {
        $data = json_decode($req->getContent(), true);
        if(array_key_exists('code', $data)) {
            $token = 'eVBlv8SKQ8unIoAL5sSPX7:APA91bGvWurbuo1oXDvdQSe_y19Py-F4llMo-70Vx04iWYDTwXPW16Egq_rSj8scTkbcTl4QVY9uZcyRcUQwsCvJCtJxN0ePfVt4apdNQ09mkqzSnOF-LeE';
            $notif = Notification::create('Refuerzo de Solicitud', $data['code'], '');
            $result = $push->sendTo($token, $notif, ['ownApp' => $data['slugApp']]);
            if(array_key_exists('sended', $result)) {
                return $this->json(['abort' => false, 'id' => $result['sended']['name']]);
            }
        }
        return $this->json(['abort' => true]);
    }
}
