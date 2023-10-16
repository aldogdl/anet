<?php

namespace App\Controller\ShopCore;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Service\SecurityBasic;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Todos los get sin Token para la app de ShopCore
 */
class GetController extends AbstractController
{

  #[Route('shop/{slug}/_ft/{uuid}', methods: ['get'])]
  public function anulandoRouteFt(String $slug, String $uuid): RedirectResponse | Response
  {

    if($slug == '') {
        return $this->json(['hola' => 'Bienvenido...']);
    }
    return $this->redirect('https://www.autoparnet.com/shop/?emp='.$slug.'&ft='.$uuid, 301);
  }

  #[Route('shop/{slug}', methods: ['get'])]
  public function anulandoRoute(String $slug): RedirectResponse | Response
  {

    if($slug == '') {
        return $this->json(['hola' => 'Bienvenido...']);
    }
    return $this->redirect('https://www.autoparnet.com/shop/?emp='.$slug, 301);
  }

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
   * Actualizamos el token de FB
  */
  #[Route('security-basic/set-tkfb/{token}/{tokPush}/{slug}/{field}/', methods:['get'])]
  public function setTokenFB(
    SecurityBasic $lock, String $token, String $tokPush, String $slug, String $field
  ): Response
  {
    $data = '';
    if($lock->isValid($token)) {
      $pathTo = $this->getParameter('dtaCtc') . $slug . '.json';
      if(is_file($pathTo)) {
        $data = file_get_contents($pathTo);
        $json = json_decode($data, true);
        $json[$field] = $tokPush;
        file_put_contents($pathTo, json_encode($json));
      }
    }

    return $this->json(['abort' => false, 'msg' => 'ok']);
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

  /**
   * Recuperamos las respuesta de una pieza por medio del UUID
  */
  #[Route('security-basic/get-respuestas/{token}/{uuid}/{slug}/', methods:['get'])]
  public function getRespuestas(
    SecurityBasic $lock, String $token, String $uuid, String $slug, 
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {

      $pathTo = $this->getParameter('prodSols');
      $deco = urldecode($uuid);
      $pathFile = $pathTo . '/' . $slug . '/' . $deco . '.json';
      if(is_file($pathFile)) {
        $data = json_decode(file_get_contents($pathFile), true);
      }
    }

    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
  }

  /** */
  #[Route('security-basic/get-all-marcas/{token}/', methods:['get'])]
  public function getAllMarcas(SecurityBasic $lock, AO1MarcasRepository $marcasEm, String $token): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $data = $marcasEm->getAllNameAsArray();
    }
    return $this->json([
      'abort'=>false, 'msg' => 'ok', 'body' => $data
    ]);
  }

  /** */
  #[Route('security-basic/get-modelos-by-marca/{token}/{idMarca}/', methods:['get'])]
  public function getModelosByMarca(
    SecurityBasic $lock, AO2ModelosRepository $modsEm, String $token, String $idMarca
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $data = $modsEm->getAllModelsNameByIdMarca($idMarca);
    }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $data]);
  }

  /** */
  #[Route('security-basic/change-pass/{token}/{idCot}/{newPass}/', methods:['get'])]
  public function changePassword(
    SecurityBasic $lock, NG2ContactosRepository $userEm, String $token, String $idCot,
    String $newPass
  ): Response
  {
    $pass = '';
    // if($lock->isValid($token)) {
      $userDql = $userEm->getContactoById($idCot);
      $user = $userDql->execute();
      if($user) {
        $pass = $userEm->encodePassword($user[0], $newPass);
        $userEm->upgradePassword($user[0], $pass);
        $pass = [
          'id' => $user[0]->getId(),
          'username' => $user[0]->getCurc(),
          'pass' => $pass,
        ];
      }
    // }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'pass' => $pass]);
  }

  /** */
  #[Route('/api/shop-core/get-tkwa/', methods:['get'])]
  public function getTkWa(): Response
  {
    $pathToken = $this->getParameter('tkwaconm');
    $token  = file_get_contents($pathToken);
    return $this->json(['abort'=>false, 'msg' => 'ok', 'tkwa' => $token]);
  }

}
