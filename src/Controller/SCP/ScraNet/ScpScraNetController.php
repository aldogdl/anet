<?php

namespace App\Controller\SCP\ScraNet;

use App\Repository\AO1MarcasRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class ScpScraNetController extends AbstractController
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

  /** */
  #[Route('scp/scranet/get-all-marcas/', methods: ['get'])]
  public function getAllMarcas( AO1MarcasRepository $mrksEm ): Response
  {
    $dql = $mrksEm->getAllMarcas();
    return $this->json([
      'abort' => false, 'msg' => 'ok',
      'body' => $dql->getScalarResult()
    ]);
  }

  /** */
  #[Route('scp/scranet/set-marca/', methods: ['post'])]
  public function setMarca(Request $req, AO1MarcasRepository $mrksEm): Response
  {
    $data = $this->toArray($req, 'data');
    $result = $mrksEm->setMarca($data);
    return $this->json($result);
  }


}
