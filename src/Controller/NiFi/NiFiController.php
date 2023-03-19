<?php

namespace App\Controller\NiFi;

use App\Repository\OrdenesRepository;
use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class NiFiController extends AbstractController
{
    /**
     * 
     */
    #[Route('nifi/', name: 'home')]
    public function index(): Response
    {
        return $this->json(['hola' => 'Bienvenido...']);
    }

    /**
     * 
     */
    #[Route('nifi/get-ordenes-ids/', methods: ['GET'])]
    public function getOrdenesIds(OrdenesRepository $em): Response
    {
        $ids = [];
        $result = $em->findAll();
        if($result) {
            foreach ($result as $orden) {
                if(!in_array($orden->getId(), $ids)) {
                    $ids[] = $orden->getId();
                }
            }
        }
        return $this->json(['abort'=> true, 'msg' => $ids]);
    }

    /**
     * 
     */
    #[Route('nifi/orden/{id}/', methods: ['GET'])]
    public function getOrden(WebHook $wh, OrdenesRepository $em, int $id): Response
    {
        $msg = 'No se encontró la orden con ID: '.$id;
        $result = $em->find($id);
        if($result) {
            $toFile = $result->toArray();
            $pathNifi = $this->getParameter('nifiFld');
            $filename = $pathNifi.$id.'.json';
            $payload = [
                "evento" => "creada_solicitud",
                "source" => $id.'.json'
            ];

            if($toFile) {
                $content = file_put_contents($filename, json_encode($toFile));
                if($content > 0) {
                    $wh->sendMy($payload, $pathNifi);
                }
            }
            $msg = 'Guardada la Orden con el ID: '.$id;
        }
        return $this->json(['abort'=> true, 'msg' => $msg]);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('wa/wh/', methods: ['GET', 'POST'])]
    public function verifyWa(Request $req): Response
    {
        if($req->getMethod() == 'GET') {

            $verify = $req->query->get('hub_verify_token');
            if($verify == $this->getParameter('getWaToken')) {
    
                $mode = $req->query->get('hub_mode');
                $challenge = $req->query->get('hub_challenge');
        
                return new Response($challenge);
            }
        }

        if($req->getMethod() == 'POST') {

            $filename = round(microtime(true) * 1000);
            $has = $req->getContent();
            if($has) {
                file_put_contents('mesaje_'.$filename.'.json', $has);
            }
            return new Response('', 200);
        }
        

        return $this->json( [], 500 );
    }

}
