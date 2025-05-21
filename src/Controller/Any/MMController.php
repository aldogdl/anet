<?php

namespace App\Controller\Any;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/any-mm')]
class MMController extends AbstractController
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

    #[Route('/marcas', methods: ['get', 'post', 'delete'])]
    public function marcas(Request $req): Response
    {
        if($req->getMethod() == 'POST') {
            $data = $req->getContent();
            if($data) {
                $data = json_decode($data, true);
                return $this->json(['abort' => false, 'body', '$recibidos'.count($data)]);
            }
        }
        return $this->json(['hola' => 'Bienvenido', 'en que podemos atenderte?']);
    }

}
