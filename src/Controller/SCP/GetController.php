<?php

namespace App\Controller\SCP;

use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{
    
    #[Route('scp/get-all-contactos-by/{tipo}/', methods:['get'])]
    public function getAllContactsBy(
        NG2ContactosRepository $contactsEm,
        string $tipo
    ): Response
    {   
        $dql = $contactsEm->getAllContactsBy($tipo);
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $dql->getScalarResult()
        ]);
    }
    
    #[Route('scp/get-orden-by-id/{idOrden}/', methods:['get'])]
    public function getOrdenById(OrdenesRepository $ordEm, $idOrden): Response
    {   
        $dql = $ordEm->getDataOrdenById($idOrden);
        $data = $dql->getScalarResult();
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => (count($data) > 0) ? $data[0] : []
        ]);
    }

}
