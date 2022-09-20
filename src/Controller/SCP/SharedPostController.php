<?php

namespace App\Controller\SCP;

use App\Repository\NG1EmpresasRepository;
use App\Repository\NG2ContactosRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SharedPostController extends AbstractController
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

  /***/
  #[Route('scp/guardar-datos-empcontac/', methods:['post'])]
  public function seveDataContact(
    Request $req, NG1EmpresasRepository $empEm,
    NG2ContactosRepository $contactsEm,
  ): Response
  {
    $data = $this->toArray($req, 'data');
    $isEmpresa = false;
    if(array_key_exists('empresa', $data)) {
      if(array_key_exists('contacto', $data)) {
        $isEmpresa = true;
      }
    }

    if($isEmpresa) {
      $result = $empEm->seveDataContact($data['empresa']);
      if(!$result['abort']) {
        $data['contacto']['empresaId'] = $result['body'];
        $result = $contactsEm->seveDataContact($data['contacto']);
      }
    }else{
      $result = $contactsEm->seveDataContact($data);
    }
    return $this->json($result);
  }

}
