<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\ProductRepository;
use App\Service\Crawlers\ToRadec;
use App\Service\SecurityBasic;
use App\Service\ShopCore\DataSimpleMlm;
use App\Service\ShopCore\ShopCoreSystemFileService;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Todos los get sin Token para la app de ShopCore
 */
class GetCrawController extends AbstractController
{
  // https://www.radec.com.mx/catalogo?search=faro+audi+a3&op=Buscar
  #[Route('shop/craw-rdec/{url}/', methods: ['get'])]
  public function getPage(String $url, ToRadec $craw): Response
  {
    if($url == '') {
      return $this->json([]);
    }
    $html = $craw->load($url);
    return new Response($html, 200);
  }

}
