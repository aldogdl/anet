<?php

namespace App\Controller\AnyShop;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\DataSimpleMlm;

class GetController extends AbstractController
{
  /** 
  * Recuperamos los datos del dueño del catalogo
  */
  #[Route('get-data-own/{slug}/', methods:['GET'])]
  public function item(DataSimpleMlm $mlm, String $slug): Response {

    return $this->json($mlm->getDataOwn($slug));
  }
}
