<?php

namespace App\Controller\Any;

use Symfony\Component\Filesystem\Path;
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

    /**
     * Endpoint para subir las imagenes desde el form del catalogo
     */
    #[Route('/image', methods: ['POST'])]
    public function imagesUP(Request $req): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['abort' => true, 'body' => 'X No se ha subido la foto'], 401);
        }
        
        $slug = $req->request->get('slug') ?? null;
        $ikuItem = $req->request->get('ikuItem') ?? null;
        $key = $req->request->get('key') ?? null;
        $file = $req->files->get('file');
        $getTunnel = $req->request->get('tunels') ?? 'no';

        if (!$slug || !$ikuItem || !$key || !$file) {
            return $this->json(['abort' => true, 'body' => 'Parámetros incompletos'], 400);
        }

        $prodSols = $this->getParameter(AnyPath::$PRODSOLS);
        $path = Path::canonicalize($prodSols.'/'.$slug.'/'.$ikuItem);

        if (!file_exists($path)) {
            try {
                mkdir($path, 0755, true);
            } catch (\Throwable $th) {
                return $this->json(['abort' => true, 'body' => 'X Error al crear carpeta' . $path], 402);
            }
        }
        
        try {
            $originalFilename = basename($file->getClientOriginalName());
            $file->move($path, $originalFilename);
        } catch (\Throwable $e) {
            return $this->json(['abort' => true, 'body' => 'Error al mover archivo: '.$e->getMessage()], 500);
        }

        $results = [
            'abort' => false,
            'body' => 'Imagen guardada correctamente',
            'filename' => $originalFilename,
        ];
        if($getTunnel == 'si') {
            $ngkf = $this->getParameter(AnyPath::$NGKF);
            $path = Path::canonicalize($ngkf);
            if (file_exists($path)) {
                $results['tunnels'] = json_decode(file_get_contents($path), true);
            }
        }
        return $this->json($results);
    }

    /** */
    #[Route('/sol', methods: ['get', 'post', 'delete'])]
    public function itemSol(Request $req, ItemPubRepository $repo, Fsys $fsys): Response
    {
    
        if( $req->getMethod() == 'POST' ) {

            $data = json_decode($req->getContent(), true);

            if($data) {

                if (!$data || !isset($data['sl'], $data['sols'])) {
                    return $this->json(['abort' => true, 'body' => 'Datos incompletos'], 400);
                }
                $slug = $data['sl'];
                $userName = $data['us'];
                $userWaId = $data['wi'];
                $userMail = (array_key_exists('ma', $data)) ? $data['ma'] : '';
                $solicitud = $data['sols'];

                if (!$slug || !$userWaId || !$solicitud) {
                    return $this->json(['abort' => true, 'body' => 'Parámetros incompletos'], 400);
                }

                $prodSols = $this->getParameter(AnyPath::$PRODSOLS);
                $path = Path::canonicalize($prodSols.'/'.$slug.'/sols.json');
                if (!file_exists($path)) {
                    file_put_contents($path, json_encode([
                        $userWaId => [
                            'name' => $userName,
                            'mail' => $userMail,
                            'sols' => [$solicitud],
                        ]
                    ]));
                    return $this->json(['abort' => false, "body" => 'Guardao con éxito']);
                }
                
                $sols = json_decode(file_get_contents($path), true);
                if(!array_key_exists($userWaId, $sols)) {
                    $sols[$userWaId] = [
                        'name' => $userName,
                        'mail' => $userMail,
                        'sols' => [$solicitud],
                    ];
                    file_put_contents($path, json_encode($sols));
                    return $this->json(['abort' => false, "body" => 'Guardao con éxito']);
                }
                
                if(array_key_exists('notiff', $data)) {
                    // TODO 
                    // No se logro enviar la notificacion desde el cliente hacia el core
                }
                if($sols[$userWaId]['name'] == $userName) {
                    $sols[$userWaId]['sols'][] = $solicitud;
                    file_put_contents($path, json_encode($sols));
                    return $this->json(['abort' => false, "body" => 'Guardao con éxito']);
                }
            }
            
        } elseif( $req->getMethod() == 'GET' ) {

            $slug = $req->query->get('slug') ?? '';
            $ownWaid = $req->query->get('ownWaid') ?? '';
            if(!$slug || !$ownWaid) {
                return $this->json([]);
            }

            $prodSols = $this->getParameter(AnyPath::$PRODSOLS);
            $path = Path::canonicalize($prodSols.'/'.$slug.'/sols.json');
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if($content) {
                    $content = json_decode($content, true);
                    return (array_key_exists($ownWaid, $content))
                        ? $this->json($content[$ownWaid]['sols']) : [];
                }
            }
            return $this->json([]);
        }
        return $this->json(['abort' => true, 'body' => 'Error inesperado']);
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
