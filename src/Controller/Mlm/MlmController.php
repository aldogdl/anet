<?php

namespace App\Controller\Mlm;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\RedirectResponse;

class MlmController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET'])]
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
                return $this->redirect(
                    'https://autoparnet.com/shop/?emp='.$slug.'&action=mlm:'.$state,
                    301
                );
            }
        }
        return new Response('Bienvenido a ANET->MLM', 200);
    }

    /**
     * Endpoint para la recuperar la conx
     */
    #[Route('mlm/get-codes/', methods: ['GET'])]
    public function mlmGetCodes(Request $req): Response
    {
        if($req->getMethod() == 'GET') {
            $theGet = $this->getParameter('anetMlm');
            $data = json_decode(file_get_contents($theGet), true);
            return $this->json($data);
        }
        return new Response(400);
    }

    /**
     * Al vincular mlm con anetShop se crea un json con los datos de dicha
     * vinculacion por lo tanto se recuperan desde la app AnetShop y se
     * eliminan inmediatamente.
     */
    #[Route('mlm/get-code-auth/{slug}/', methods: ['GET'])]
    public function mlmGetCodeAuth(Request $req, String $slug): Response
    {
        if($req->getMethod() == 'GET') {
            $path = 'mlm_'.$slug.'.json';
            $data = json_decode(file_get_contents($path), true);
            if($data) {
                unlink($path);
            }
            return $this->json($data);
        }
        return new Response(400);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/notifications/', methods: ['GET', 'POST'])]
    public function notisMlm(Request $req): Response
    {
        file_put_contents('mlm_simple_'.time().'.json', '');
        file_put_contents('mlm_wh_'.time().'.json', json_encode($req->getContent()));
        return new Response('listo MLM');
    }

}
