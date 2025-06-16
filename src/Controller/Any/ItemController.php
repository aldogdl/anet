<?php

namespace App\Controller\Any;

use Symfony\Component\Filesystem\Path;
use App\Repository\PubsRepository;
use App\Repository\SolsRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\PublicAssetUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/any-item')]
class ItemController extends AbstractController
{
    /**
     * Endpoint para subir las imagenes desde el form del catalogo
     */
    #[Route('/image', methods: ['POST'])]
    public function imagesUP(Request $req, PublicAssetUrlGenerator $urlGen): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['abort' => true, 'body' => 'X No se ha subido la foto'], 401);
        }
        
        $ikuItem = $req->request->get('ikuItem') ?? null;
        $key = $req->request->get('key') ?? null;
        $file = $req->files->get('file');

        if (!$ikuItem || !$key || !$file) {
            return $this->json(['abort' => true, 'body' => 'Parámetros incompletos'], 400);
        }

        $prodSols = $this->getParameter(AnyPath::$PRODSOLS);
        $path = Path::canonicalize($prodSols);

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
            return $this->json(['abort' => true, 'body' => 'X Error al mover archivo: '.$e->getMessage()], 500);
        }

        // Un refuerzo para guardarlo
        $path = Path::canonicalize($prodSols.'/'.$originalFilename);
        if (!file_exists($path)) {
            try {
                $file->move($path, $originalFilename);
            } catch (\Throwable $th) {
                return $this->json(['abort' => true, 'body' => 'X Error al mover archivo' . $path], 402);
            }
        }
        $url = $urlGen->generate($path);
        $results = [
            'abort' => false,
            'body' => $url,
            'filename' => $originalFilename,
        ];

        return $this->json($results);
    }

    /** */
    #[Route('/sol', methods: ['get', 'post', 'delete'])]
    public function itemSol(Request $req, SolsRepository $em): Response
    {
        if( $req->getMethod() == 'POST' ) {

            $data = $req->getContent();
            
            if($data) {
                
                $data = json_decode($data, true);
                if (!$data || !array_key_exists('sol', $data) || !isset($data['sl'], $data['sol'])) {
                    return $this->json(['abort' => true, 'body' => 'Datos incompletos'], 400);
                }

                $newId = $em->setSol($data);
                if($newId == 0) {
                    return $this->json(['abort' => true, 'body' => 'Inténtalo nuevamente'], 403);
                }
                if(array_key_exists('notiff', $data)) {
                    // TODO 
                    // No se logro enviar la notificacion desde el cliente hacia el core
                }
                return $this->json(['abort' => false, 'body' => $newId]);
            }
            
        } elseif( $req->getMethod() == 'GET' ) {

            // Si viene el slug
            $slug = $req->query->get('slug') ?? '';
            // Si viene el ciku es que es un colaborador que quiere sus solicitudes
            $cIku = $req->query->get('ciku') ?? '';
            // El iku del usuario que quiere sus solicitudes
            $usIku = $req->query->get('usiku') ?? '';
            if(!$slug) {
                return $this->json([]);
            }
            if($usIku) {
                $res = $em->getMiSolicitudes($slug, $usIku);
            }
            return $this->json([]);
        }
        return $this->json(['abort' => true, 'body' => 'Error inesperado']);
    }

    /** */
    #[Route('/pub', methods: ['get', 'post', 'delete'])]
    public function itemPub(Request $req, PubsRepository $repo, Fsys $fsys): Response
    {
        if( $req->getMethod() == 'POST' ) {
            $data = $req->getContent();
            if($data) {
                $data = json_decode($data, true);
                if(array_key_exists('list', $data)) {
                    $res = $repo->setPubs($data);
                    if($res != 0) {
                        return $this->json(['abort' => false, "body" => $res]);
                    }
                }else{
                    $res = $repo->setPub($data);
                    if($res != 0) {
                        return $this->json(['abort' => false, "id" => $res, "body" => 'Guardado con éxito']);
                    }
                }
            }
            
        } elseif( $req->getMethod() == 'GET' ) {
            $items = [];
            return $this->json($items, 200);
        }

        return $this->json(['abort' => true, 'body' => 'Error inesperado']);
    }

    /** */
    #[Route('/cat/{slug}', methods: ['get'])]
    public function itemCat(Request $req, PubsRepository $repo, Fsys $fsys, String $slug): Response
    {
        if( $req->getMethod() == 'GET' ) {
            $items = $fsys->getPackageOf($slug);
            if(!$items) {
                // Recuperamos de cache o de DB el paquete del dia
                $items = $repo->buildPakegeOf($slug);
                $fsys->setPackageOf($slug, $items);
            }

            return $this->json($items);
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
