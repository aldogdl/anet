<?php

namespace App\Controller;

use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
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
}
