<?php

namespace App\Controller\NiFi;

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
