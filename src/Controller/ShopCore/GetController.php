<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;

class GetController extends AbstractController
{

  /** */
  #[Route('security-basic/get-data-ctc/{token}/{slug}/', methods:['get'])]
  public function getDataContact(
    SecurityBasic $lock, String $token, String $slug
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('dtaCtc') . $slug . '.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
      }
    }

    return new Response($data);
  }

}
