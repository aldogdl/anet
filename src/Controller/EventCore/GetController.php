<?php

namespace App\Controller\EventCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\JsonException;

use App\Repository\NG2ContactosRepository;
use App\Service\AnetShop\AnetShopSystemFileService;

class GetController extends AbstractController
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

    #[Route('event-core/get-user-by-campo/', methods:['get'])]
    public function getUserByCampo(Request $req, NG2ContactosRepository $contacsEm): Response
    {
      $campo = $req->query->get('campo');
      $valor = $req->query->get('valor');
      $user = $contacsEm->findOneBy([$campo => $valor]);
      $result = [];
      $abort = true;
      if($user) {
        $abort = false;
        $result = $contacsEm->toArray($user);
      }
      return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
    }
  
    /** */
    #[Route('event-core/the-solicitante/', methods: ['GET'])]
    public function theSolicitantes(Request $req, AnetShopSystemFileService $fSys): Response
    {
        $result = ['abort' => true, 'body' => 'Error Inesperado'];
        if($req->getMethod() == 'GET') {
            $solz = $fSys->getAllSolicitantes();
            $result = ['abort' => false, 'msg' => 'Results: '.count($solz), 'body' => $solz];
        }

        return $this->json($result);
    }

    /** */
    #[Route('event-core/conv-free/{waid}/', methods: ['GET', 'DELETE'])]
    public function putCotInConvFree(Request $req, String $waid): Response
    {
        if($req->getMethod() == 'GET') {
            $filename = 'conv_free.'.$waid.'.cnv';
            file_put_contents($filename, '');
            return $this->json(['code' => $filename]);
        }

        if($req->getMethod() == 'DELETE') {

            $filename = 'conv_free.'.$waid.'.cnv';
            if(is_file($filename)) {
                unlink($filename);
                return $this->json(['code' => 'exit']);
            }
        }

        return $this->json(['code' => 'error']);
    }
  
    /** */
    #[Route('event-core/data-conmutador/', methods: ['GET', 'POST'])]
    public function getDataConmutador(Request $req): Response
    {
        $filename = 'tkwaconm.json';
        if($req->getMethod() == 'GET') {
            $data = file_get_contents($filename);
            return $this->json(['code' => base64_encode($data)]);
        }
        
        if($req->getMethod() == 'POST') {
            $data = $this->toArray($req, 'data');
            file_put_contents($filename, json_encode($data));
        }

        return $this->json(['code' => 'error']);
    }
  
}
