<?php

namespace App\Controller\Harbi;

use App\Repository\OrdenesRepository;
use App\Service\CentinelaService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{
    
    #[Route('harbi/save-ip-address-harbi/{dataConnection}/', methods:['get'])]
    public function saveIpAdressHarbi($dataConnection): Response
    {   
        file_put_contents('harbi_connx.txt', $dataConnection);
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => 'save'
        ]);
    }

    /**
     * Checamos la ultima version del archivo de seguimiento de las ordenes
     */
    #[Route('harbi/check-version-centinela/{lastVersion}/', methods:['get'])]
    public function checkVersionCentinela(CentinelaService $centinela, $lastVersion): Response
    {   
        $isSame = $centinela->isSameVersion($lastVersion);
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $isSame
        ]);
    }

    /**
     * Recuperamos la orden por medio de su ID
     */
    #[Route('harbi/get-orden-by-id/{idOrden}/', methods:['get'])]
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
