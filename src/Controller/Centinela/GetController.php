<?php

namespace App\Controller\Centinela;

use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{

    /** */
    #[Route('centinela/get-data-orden-by-id/{idOrden}/', methods:['get'])]
    public function getDataOrdenById(
        OrdenesRepository $ordenesEm,
        string $idOrden
    ): Response
    {
        $dql = $ordenesEm->getDataOrdenById($idOrden);
        $orden = $dql->getScalarResult();
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => (count($orden) > 0) ? $orden[0] : []
        ]);
    }

    /** */
    #[Route('centinela/get-data-pieza-by-id/{idPieza}/', methods:['get'])]
    public function getDataPiezaById(
        OrdenPiezasRepository $pzasEm,
        string $idPieza
    ): Response
    {
        $dql = $pzasEm->getDataPiezaById($idPieza);
        $pieza = $dql->getScalarResult();
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => (count($pieza) > 0) ? $pieza[0] : []
        ]);
    }

}
