<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\WebHook;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeController extends AbstractController
{
    /**
     * Obtenemos el request contenido decodificado como array
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function toArray(Request $req, String $campo): array
    {
        $content = $req->request->get($campo);
        try {
        $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
        throw new JsonException(sprintf('No se puede decodificar el body, "%s".', get_debug_type($content)));
        }

        if (!\is_array($content)) {
        throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
        }
        return $content;
    }

    #[Route('/', methods: ['get'])]
    public function index(): Response
    {
        return $this->json(['hola' => 'Bienvenido...']);
    }

    #[Route('/{slug}/shop/', methods: ['get'])]
    public function anulandoRoute(String $slug): RedirectResponse | Response
    {
        if($slug == '') {
            return $this->json(['hola' => 'Bienvenido...']);
        }
        return $this->redirect('https://www.autoparnet.com/shop?emp='.$slug, 301);
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

    #[Route('backcore/update-url-nfrok/{pass}/{key}/', methods: ['GET'])]
    public function updateUrlNfrok( string $pass, string $key ): Response
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

    /**
     * Hacemos una prueba hacia el broker --front-door-- desde back-core
     */
    #[Route('backcore/make-test/{token}/', methods:['post'])]
    public function testToSistemNifi(Request $req, WebHook $wh, String $token): Response
    {
        $elToken = $this->getParameter('getAnToken');
        $data = $this->toArray($req, 'data');
        if($elToken == $token) {

            if(array_key_exists('evento', $data)) {
                $data['status'] = 'recibido';
                // Enviamos el evento de nueva orden
                $wh->sendMy('backcore\\make-test', $data['evento'], $data);
                return $this->json($data);
            }
        }

        $data['status'] = 'fail';
        return $this->json($data);
    }

}
