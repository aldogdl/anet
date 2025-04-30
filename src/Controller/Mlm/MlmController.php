<?php

namespace App\Controller\Mlm;

use App\Service\SecurityBasic;
use App\Service\DataSimpleMlm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MlmController extends AbstractController
{
    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/notifications/', methods: ['GET', 'POST'])]
    public function notisMlm(Request $req): Response
    {
        file_put_contents('mlm_wh_'.time().'.json', json_encode($req->getContent()));
        return new Response('listo MLM');
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req): Response
    {
        $slug = $req->query->get('state');
        $code = $req->query->get('code');
        if(mb_strlen($code) > 10) {
            file_put_contents('mlm_'.$slug.'.txt', $code);
            return new Response(file_get_contents('shop/mlm_exito.html'));
        }
        return new Response('Bienvenido a ANY->MLM', 200);
    }

    /**
     * Al vincular mlm con anyShop se crea un json con los datos de dicha
     * vinculacion por lo tanto se recuperan desde la app AnyShop y se
     * eliminan inmediatamente.
     */
    #[Route('mlm/parse-cot-token/{slug}/', methods: ['GET'])]
    public function mlmParseCodeToken(Request $req, DataSimpleMlm $mlm, String $slug): Response
    {
        if($req->getMethod() == 'GET') {

            $path = 'mlm_'.$slug.'.txt';
            if(!is_file($path)) {
                return $this->json(['abort' => false, 'body' => ['error' => 'X Aun no llega']]);
            }

            try {
                $code = file_get_contents($path);
                if($code) {
                    $isOk = $mlm->parseCodeToToken($code, $slug);
                    if(count($isOk) > 0) {
                        unlink($path);
                        return $this->json($isOk);
                    }
                }
                return $this->json(['abort' => true, 'body' => ['error' => 'X Error en los datos']]);
            } catch (\Throwable $th) {
                return $this->json(['abort' => true, 'body' => ['error' => 'X ' . $th->getMessage()]]);
            }
        }
        
        return $this->json(['abort' => true, 'body' => ['error' => 'X Error desconocido']]);
    }

}
