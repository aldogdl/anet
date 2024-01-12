<?php

namespace App\Controller\EventCore;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\EventCore\EventCoreSystemFileService;

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

  /** 
   * Guardar el mensaje prefabricado del rastreo de una solicitud
  */
  #[Route('api/event-core/save-prod-track/', methods:['post'])]
	public function saveProdTrack(Request $req, EventCoreSystemFileService $sysFile): Response
	{
    $result = ['abort' => false];
    $data = $this->toArray($req, 'data');
    if(array_key_exists('id', $data)) {

      $res = $sysFile->setProdTrack($data);
      if(mb_strpos($res, 'X ') !== false) {
        $result['abort']  = true;
        $result['msg']  = 'error';
      }
      $result['body'] = $res;
    }

    // Como proteccion al sistema, creamos la carpeta tracking en coso de no existir
    $pathTracking = $this->getParameter('tracking');
    if(!is_dir($pathTracking)) {
      mkdir($pathTracking);
    }
    
	  return $this->json($result);
	}

}
