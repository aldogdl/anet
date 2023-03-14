<?php

namespace App\Controller\NiFi;

use App\Repository\OrdenesRepository;
use App\Service\WebHook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
            if($toFile) {
                $pathNifi = $this->getParameter('nifiFld');
                $content = file_put_contents($filename, json_encode($toFile));
                if($content > 0) {
                    $wh->sendMy('solicitud_creada', $id.'.json');
                }
            }
            $msg = 'Guardada la Orden con el ID: '.$id;
        }
        return $this->json(['abort'=> true, 'msg' => $msg]);
    }
}
