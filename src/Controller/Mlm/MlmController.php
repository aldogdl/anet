<?php

namespace App\Controller\Mlm;

use App\Service\Mlm\MlmService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\WA\Dom\WaMessageDto;
use App\Service\WA\WaService;
use App\Service\WapiRequest\ProcesarMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MlmController extends AbstractController
{
    private $folder = '9d3468bd1f51f6c9546a23213eb649ac2a040ac29d196f96859926bf3c46fc6f';

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/wh/notifications/', methods: ['GET'])]
    public function notisMlm(): Response
    {
        return new Response('listo MLM');
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req, MlmService $mlmServ): Response
    {
        $met = $req->getMethod();
        file_put_contents('mlm_'.$met.'.json', json_encode([
            'querys' => $req->query->all(),
            'met'   => $met,
            'ips' => $req->getClientIps(),
            'inf' => $req->getPathInfo(),
            'host' => $req->getHttpHost(),
            'body' => $req->getContent()
        ]));

        if($met == 'GET') {

            $verify = $req->query->get('code_challenge');
            if($verify == $this->folder) {
                return new Response($this->folder);
            }

            $code = $req->query->get('code');
            if(strlen($code) > 10) {
                $mlmServ->codeAuth = $code;
                $res = $mlmServ->send();
            }
        }

        if($met == 'POST') {
            file_put_contents('mlm_POST_'.$met.'.json', json_encode([
                'body' => $req->getContent()
            ]));
        }
        return new Response();
    }

    /** */
    #[Route('shop/mlm/', methods: ['get'])]
    public function anulandoRoute(): RedirectResponse | Response
    {
    //   if($slug == '') {
    //       return $this->json(['hola' => 'Bienvenido...']);
    //   }
      return $this->redirect('https://www.autoparnet.com/shop/?emp=iksan', 301);
    }
  
}
