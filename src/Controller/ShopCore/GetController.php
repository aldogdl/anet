<?php

namespace App\Controller\ShopCore;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;

/**
 * Todos los get sin Token para la app de ShopCore
 */
class GetController extends AbstractController
{

  /** 
   * Recuperamos los datos del cotizador desde el archivo json
  */
  #[Route('security-basic/get-data-ctc/{token}/{slug}/', methods:['get'])]
  public function getDataContact(
    SecurityBasic $lock, String $token, String $slug
  ): Response
  {
    $data = '';
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('dtaCtc') . $slug . '.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
      }
    }

    return new Response($data);
  }

  /** 
   * Recuperamos el inventario del cotizador desde el archivo json
  */
  #[Route('security-basic/get-inv-ctc/{token}/{waId}/', methods:['get'])]
  public function getInvContact(
    SecurityBasic $lock, String $token, String $waId
  ): Response
  {
    $data = '';
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('invCtc') . $waId . '_up.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
      }
    }

    return new Response($data);
  }

  /** */
  #[Route('security-basic/get-all-marcas/{token}', methods:['get'])]
  public function getAllMarcas(
    SecurityBasic $lock, AO1MarcasRepository $marcasEm, String $token
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $data = $marcasEm->getAllNameAsArray();
    }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
  }

  /** */
  #[Route('security-basic/get-modelos-by-marca/{token}/{idMarca}/', methods:['get'])]
  public function getModelosByMarca(
    SecurityBasic $lock, AO2ModelosRepository $modsEm, String $token, int $idMarca
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $data = $modsEm->getAllModelsNameByIdMarca($idMarca);
    }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
  }

}
