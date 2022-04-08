<?php

namespace App\Controller\SCP\Solicitudes;

use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Service\CentinelaService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class PostController extends AbstractController
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
            throw new JsonException('No se puede decodificar el body.', $e->getCode(), $e);
        }
        if (!\is_array($content)) {
            throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
        }
        return $content;
    }

    #[Route('scp/change-stt-to-orden/', methods:['post'])]
    public function changeSttToOrden(
        Request $req,
        OrdenesRepository $ordsEm,
        CentinelaService $centinela
    ): Response
    {   
        $data = $this->toArray($req, 'data');
        $ordsEm->changeSttOrdenTo($data['orden'], $data);
        $centinela->setNewSttToOrden($data);
        // ToDoPush
        // hacer una notificación push al solicitante del cambio de estatus de la orden
        return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => []]);
    }

}
