<?php

namespace App\Controller\Mlm;

use App\Service\SecurityBasic;
use App\Service\AnetShop\DataSimpleMlm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MlmController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req): Response
    {
        $state = $req->query->get('state');

        if(mb_strpos($state, ':') !== false) {

            $code = $req->query->get('code');
            if(mb_strlen($code) > 10) {
                $partes = explode(':', $state);
                $action = $partes[0];
                $slug = $partes[1];
                file_put_contents('mlm_'.$slug.'.json', json_encode([
                    'code' => $code, 'action' => $action, 'slug' => $slug
                ]));
                return new Response(file_get_contents('shop/mlm_exito.html'));
            }
        }
        return new Response('Bienvenido a ANY->MLM', 200);
    }

    /**
     * Endpoint para la recuperar la conx para mlm
     */
    #[Route('mlm/get-codes/{token}/{slug}/', methods: ['GET'])]
    public function mlmGetCodes(Request $req, SecurityBasic $lock, DataSimpleMlm $mlm, String $token, String $slug): Response
    {
        if($req->getMethod() == 'GET') {
            if($lock->isValid($token)) {
                $data = $mlm->getCode($slug);
                return $this->json($data);
            }
        }
        return new Response(400);
    }

    /**
     * Al vincular mlm con anyShop se crea un json con los datos de dicha
     * vinculacion por lo tanto se recuperan desde la app AnyShop y se
     * eliminan inmediatamente.
     */
    #[Route('mlm/get-code-auth/{slug}/', methods: ['GET'])]
    public function mlmGetCodeAuth(Request $req, String $slug): Response
    {
        if($req->getMethod() == 'GET') {
            $path = 'mlm_'.$slug.'.json';
            $data = file_get_contents($path);
            if($data) {
                unlink($path);
            }
            return $this->json(['deco' => base64_encode($data)]);
        }
        return new Response(400);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/notifications/', methods: ['GET', 'POST'])]
    public function notisMlm(Request $req): Response
    {
        file_put_contents('mlm_simple_'.time().'.json', '');
        file_put_contents('mlm_wh_'.time().'.json', json_encode($req->getContent()));
        return new Response('listo MLM');
    }

}
