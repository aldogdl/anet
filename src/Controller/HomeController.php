<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', methods: ['get'])]
    public function index(): Response
    {
        return $this->json(['hola' => 'Bienvenido...']);
    }

    #[Route('home-controller/get-data-connection/{pass}/', methods: ['get'])]
    public function getAllContactsBy( string $pass ): Response {

        if($pass == '2536H') {
            $path = $this->getParameter('harbiConnx');
            $data = json_decode(file_get_contents($path), true);
    
            return $this->json([
                'abort'=> false, 'msg'  => 'ok', 'body' => $data
            ]);
        }
        return $this->json(['abort'=> true, 'msg' => 'Mal-Bad', 'body' => 'Hola Intruso...']);
    }

    #[Route('backcore/update-url-front-door/{pass}/{key}/', methods: ['GET'])]
    public function updateUrlFrontDoor( string $pass, string $key ): Response
    {
        $res = base64_decode($key);
        if($res == $this->getParameter('getAnToken')) {
            $hash = file_put_contents(
                '../front_door/front_door.txt/front_door.txt',
                $pass
            );
            if($hash > 0) {
                return $this->json(['abort'=> false, 'msg' => 'ok']);
            }
        }
        return $this->json(['abort'=> true, 'msg' => 'Mal-Bad', 'body' => 'Hola Intruso...']);
    }
}
