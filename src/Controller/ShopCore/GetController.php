<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
        $content = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        $content['lastEntrie'] = new \DateTime('now');
        file_put_contents($pathTo, json_encode($content));
        $data = file_get_contents($pathTo);
        $data = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
      }
    }

    return $this->json($data);
  }

}
