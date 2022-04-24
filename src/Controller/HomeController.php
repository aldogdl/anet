<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->json(['hola' => 'bienvendo']);
    }

    #[Route('home-controller/get-data-connection/{pass}/', methods: ['get'])]
    public function getAllContactsBy(
        string $pass
    ): Response {

        $path = $this->getParameter('harbiConnx');
        $data = json_decode(file_get_contents($path), true);

        return $this->json([
            'abort'=> array_key_exists($pass, $data) ? false : true,
            'msg'  => array_key_exists($pass, $data) ? 'ok' : 'ERROR',
            'body' => array_key_exists($pass, $data) ? $data[$pass] : 'ERROR'
        ]);
    }
}
