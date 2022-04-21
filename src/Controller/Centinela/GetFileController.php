<?php

namespace App\Controller\Centinela;

use App\Service\CentinelaService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetFileController extends AbstractController
{
    #[Route('centinela/file/get-all-data-by-id/{avo}/', methods:['get'])]
    public function getAllDataByIdAvo(CentinelaService $centinela, string $avo): Response
    {
        $mini = $centinela->buildMiniFileCentinela($avo);
        return $this->json(
            ['abort'=> false, 'msg' => 'ok', 'body' => $mini]
        );
    }

}
