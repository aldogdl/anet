<?php

namespace App\Controller;

use App\Service\Pushes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->json(['hola' => 'Bienvendo...']);
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

    #[Route('push/pr/', methods: ['get'])]
    public function pushPr(Pushes $push): Response
    {
        $res = $push->sendToOwnOfIdType([
            'df0q-LdpT2-w_axhZEFKuV:APA91bHJGhBWXOMOjZ7zrY2AwXpoYuhtCyzS9XbIZoyLyCIDMYOQ2wv8x6R-FnPY7G5BiRsz3x6g_2kpcY2tN9K6ae2bHXO0U-5G7XDaqZR9XZzJSod3xSpM3noRIp5E6VsD0FgyPkNw'
        ]);
        return $this->json(['send' => $res]);
    }
}
