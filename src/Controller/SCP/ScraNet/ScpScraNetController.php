<?php

namespace App\Controller\SCP\ScraNet;

use App\Repository\AO1MarcasRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ScpScraNetController extends AbstractController
{

  /***/
  #[Route('scp/scranet/get-all-marcas/', methods: ['get'])]
  public function getAllMarcas( AO1MarcasRepository $mrksEm ): Response {

    $dql = $mrksEm->getAllMarcas();
    return $this->json([
      'abort' => false, 'msg' => 'ok',
      'body' => $dql->getScalarResult()
    ]);
  }


}
