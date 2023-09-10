<?php

namespace App\Controller\NiFi;

use App\Repository\OrdenesRepository;
use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class NiFiController extends AbstractController
{
    /**
     * 
     */
    #[Route('nifi/', name: 'home')]
    public function index(): Response
    {
        return $this->json(['hola' => 'Bienvenido...']);
    }

    /**
     * 
     */
    #[Route('nifi/get-ordenes-ids/', methods: ['GET'])]
    public function getOrdenesIds(OrdenesRepository $em): Response
    {
        $ids = [];
        $result = $em->findAll();
        if($result) {
            foreach ($result as $orden) {
                if(!in_array($orden->getId(), $ids)) {
                    $ids[] = $orden->getId();
                }
            }
        }
        return $this->json(['abort'=> true, 'msg' => $ids]);
    }

    /**
     * 
     */
    #[Route('nifi/orden/{id}/', methods: ['GET'])]
    public function getOrden(WebHook $wh, OrdenesRepository $em, int $id): Response
    {
        $msg = 'No se encontró la orden con ID: '.$id;
        $result = $em->find($id);
        if($result) {
            $toFile = $result->toArray();
            $pathNifi = $this->getParameter('nifiFld');
            $filename = $pathNifi.$id.'.json';
            $payload = [
                "evento" => "creada_solicitud",
                "source" => $id.'.json'
            ];

            if($toFile) {
                $content = file_put_contents($filename, json_encode($toFile));
                if($content > 0) {
                    $wh->sendMy('nifi\\orden', $filename, $toFile);
                }
            }
            $msg = 'Guardada la Orden con el ID: '.$id;
        }
        return $this->json(['abort'=> true, 'msg' => $msg]);
    }

    /** */
    #[Route('broker/assets/{slug}/{filename}/{path}/{cacheable}/', methods: ['GET'])]
    public function getImageWa(String $slug, String $filename, String $path, String $cacheable): Response
    {

        $path = $this->getParameter($path);
        $full = $path.$slug.'/images/'.$filename;
        $ext = explode('.', $filename);
        $types = [
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png' 
        ];

        if(count($ext) > 1) {
            $header = [
                'Content-Type' => $types[$ext[1]]
            ];
            if($cacheable == 'y') {
                $header['Cache-Control'] = 'max-age=432000';
            }
            $hoy = new \DateTime('now');
            $content = [
                'fullPath'     => $full,
                'Content-Type' => $types[$ext[1]],
                'fecha'   => $hoy->format('d-m-Y h:i:s a')
            ];
            $filename = round(microtime(true) * 1000) .'.json';
            file_put_contents('wa_get_imgs/'.$filename, json_encode($content));

            if(file_exists($full)) {
                return new BinaryFileResponse($full, 200, $header);
            }
        }

        return new JsonResponse('El Archivo no existe', 404);
    }

}
