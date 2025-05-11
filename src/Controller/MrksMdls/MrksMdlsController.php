<?php

namespace App\Controller\MrksMdls;

use App\Repository\MksMdsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MrksMdlsController extends AbstractController
{
    #[Route('/marcas', methods: ['get', 'delete', 'put'])]
    public function marcas(Request $req, MksMdsRepository $mmEm): Response
    {
        $query = $req->query->all();
        $metodo = $req->getMethod();
        if($metodo == 'GET') {
            if(array_key_exists('create', $query)) {
                $mmEm->createMarcasFromFile();
                return $this->json(['marcas' => 'Marcas creadas con éxito']);
            }
            if(array_key_exists('createfile', $query)) {
                $mmEm->buildFileMarcasAndModelos();
                return $this->json(['file' => 'Archivo de Marcas y Modelos creado con éxito']);
            }
        }else if($metodo == 'DELETE') {
            if(count($query) == 0) {
                return new Response('Se esperaba parametro mrkid', 501);
            }
        }else if($metodo == 'PUT') {
            if(count($query) == 0) {
                return new Response('Se esperaba parametro mrkid', 501);
            }
        }

        return $this->json(['hola' => 'Bienvenido', 'en que podemos atenderte?']);
    }

    /** */
    #[Route('/modelos', methods: ['get', 'delete', 'put'])]
    public function modelos(Request $req, MksMdsRepository $mmEm): Response
    {
        $query = $req->query->all();
        $metodo = $req->getMethod();
        if($metodo == 'GET') {

            if(array_key_exists('create', $query)) {
                $mmEm->createModelosFromFile();
                return $this->json(['modelos' => 'modelos creados con éxito']);
            }
            if(!array_key_exists('mrkid', $query)) {
                return new Response('Se esperaba al menos un parametro mrkid', 501);
            }
            // Ret
        }else if($metodo == 'DELETE') {
            if(count($query) == 0) {
                return new Response('Se esperaba al menos un parametro mrkid | mdlid', 501);
            }
        }else if($metodo == 'PUT') {
            if(count($query) == 0) {
                return new Response('Se esperaba al menos un parametro mrk | mdl | mrkid', 501);
            }
        }

        return $this->json(['hola' => 'Bienvenido', 'en que podemos atenderte?']);
    }

    /** */
    #[Route('/up-file', methods: ['POST'])]
    public function upFile(Request $req): Response
    {
        $data = $req->getContent();
        if($data) {
            $data = json_decode($data, true);
            file_put_contents($data['filename'], json_encode($data['content']));
        }else{
            $result['msg']  = 'X No se logró decodificar correctamente los datos de la request.';
            return $this->json($result);
        }
        return $this->json(['body' => 'ok']);
    }
}
