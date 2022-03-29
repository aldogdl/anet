<?php

namespace App\Controller\Harbi;

use App\Service\StatusRutas;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class PostController extends AbstractController
{

    #[Route('harbi/save-ruta-last/', methods:['post'])]
    public function saveRutaLast(Request $req, StatusRutas $rutas): Response
    {
        $data = json_decode( $req->request->get('data'), true );
        $rutas->setNewRuta($data);
        return $this->json([
            'abort'=>false, 'msg' => 'ok', 'body' => 'save'
        ]);
    }

}
