<?php

namespace App\Controller\SCM;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\NG2ContactosRepository;


class GetController extends AbstractController
{

  /**
   * Obtenemos el request contenido decodificado como array
   *
   * @throws JsonException When the body cannot be decoded to an array
   */
  public function toArray(Request $req, String $campo, String $content = '0'): array
  {
    if($content == '0') {
      $content = $req->request->get($campo);
    }
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
   * Recuperamos los datos del contacto para almacenarlos en disco local.
   */
  #[Route('scm/get-contacto-byid/{idContac}/', methods:['get'])]
  public function getContactoById(NG2ContactosRepository $em, String $idContac): Response
  {
    $dql = $em->getContactoById($idContac);
    $result = $dql->getScalarResult();
    $rota = count($result);
    return $this->json([
      'abort'=> ($rota > 0) ? false : true,
      'msg'  => ($rota > 0) ? 'ok' : 'Sin Resultados',
      'body' => ($rota > 0) ? $result[0] : []
    ]);
  }

}
