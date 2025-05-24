<?php

namespace App\Controller\Any;

use App\Repository\ItemPubRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/any-item')]
class ItemController extends AbstractController
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
    #[Route('/pub', methods: ['get', 'post', 'delete'])]
    public function itemPub(Request $req, ItemPubRepository $repo, Fsys $fsys): Response
    {
        if( $req->getMethod() == 'POST' ) {
            $data = $req->getContent();
            if($data) {
                $res = $repo->setPub( json_decode($data, true) );
                if(!$res['abort'] && $res['action'] == 'add') {
                    $items = $fsys->get(AnyPath::$PRODPUBS, $res['body']['os'].'+items.json');
                    $items['v'] = round(microtime(true) * 1000);
                    $items['r'][] = $res['body'];
                    $items = $fsys->set(AnyPath::$PRODPUBS, $items, $res['body']['os'].'+items.json');
                }
                return $this->json(['abort' => false, "id" => $res['body']['id'], "body" => 'Guardao con éxito']);
            }
        } elseif( $req->getMethod() == 'GET' ) {
            
        }
        return $this->json(['abort' => true, 'body' => 'Error inesperado']);
    }

    /** */
    #[Route('/dicc', methods: ['get'])]
    public function getDicc(Fsys $fsys): Response
    {
        return $this->json($fsys->getDiccionary());
    }

}
