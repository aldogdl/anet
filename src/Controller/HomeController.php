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
        return $this->json(['hola' => 'Bienvenido', 'en que podemos atenderte?']);
    }

}
