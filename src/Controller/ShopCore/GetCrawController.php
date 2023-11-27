<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\Crawlers\ToRadec;

/**
 * Todos los get sin Token para la app de ShopCore
 */
class GetCrawController extends AbstractController
{
  #[Route('api/shop/craw-rdec/{url}/', methods: ['get'])]
  public function getPage(String $url, ToRadec $craw): Response
  {
    if($url == '') {
      return new Response('', 200);
    }
    $html = $craw->load($url);
    return new Response($html, 200);
  }

}
