<?php

namespace App\Controller\ComCore;

use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
      throw new JsonException(sprintf('No se puede decodificar el body, "%s".', get_debug_type($content)));
    }

    if (!\is_array($content)) {
      throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
    }
    return $content;
  }

  /** */
  #[Route('com-core/upload-file-conn/', methods:['post'])]
  public function uploadFileConn(Request $req): Response
  {
    $response = ['abort' =>  true];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('bridgets', $data)) {
      file_put_contents($this->getParameter('comCoreFile'), json_encode($data));
      $response = ['abort' =>  false];
    }

    return $this->json($response);
  }

  /** 
   * Endpoint para realizar pruebas desde comCore y comprobar que el serverLocal
   * y la puerta de enlace funcionan
  */
  #[Route('com-core/test-com/', methods:['post'])]
  public function testCom(Request $req, WebHook $wh): Response
  {
    $response = ['abort' =>  true];
    $data = $this->toArray($req, 'data');
    if(count($data) > 0) {
      $wh->sendMy('com-core/test-com/', 'no-save', $data);
    }

    return $this->json($response);
  }

}
