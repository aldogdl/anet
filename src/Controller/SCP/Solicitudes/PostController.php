<?php

namespace App\Controller\SCP\Solicitudes;

use App\Repository\ScmOrdpzaRepository;
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

    /**
     * Registramos para la SCM la orden para la busqueda de cotizaciones de una orden
     * el centinela es actualizado en: CentinelaController::buscarCotizacionesOrden
     */
    #[Route('scp/buscar-cotizaciones-orden/', methods:['post'])]
    public function buscarCotizacionesOrden(Request $req, ScmOrdpzaRepository $scmEm): Response
    {
        $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
        $data = json_decode( $req->request->get('data'), true );
        $result = $scmEm->setBuscarCotizacionesOrden($data);
        return $this->json($result);
    }
}
