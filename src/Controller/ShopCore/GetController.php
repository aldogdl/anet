<?php

namespace App\Controller\ShopCore;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\ProductRepository;
use App\Service\SecurityBasic;
use App\Service\ShopCore\ShopCoreSystemFileService;
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
   * Buscamos productos de otros cotizadores 
  */
  #[Route('/users/{idSeller}/items/', methods:['GET'])]
  public function items(Request $req, String $idSeller, ProductRepository $emProd): Response
  {
    $criterio = $req->query->get('q');

    if(strlen($criterio) > 0) {
      $dql = $emProd->searchProducts( $idSeller, $criterio, [] );
      $products = $dql->getArrayResult();
      if(count($products) > 0) {
        $products = $emProd->reFiltro($products);
      }
    }else{
      $dql = $emProd->getAllProductsBySellerId( $idSeller );
      $products = $dql->getArrayResult();
    }

    return $this->json(['abort' => true, 'msg' => trim($criterio), 'body' => $products]);
  }

  /** 
   * Buscamos productos de otros cotizadores y/o coinsidencias
  */
  #[Route('/users/{idSeller}/items/search/', methods:['GET', 'POST'])]
  public function searchItem(Request $req, String $idSeller, ProductRepository $emProd): Response
  {
    $attr = [];
    $criterio = $req->query->get('q');
    if($req->getMethod() == 'POST') {
      $attr = json_decode($req->request->get('data'), true);
    }

    $dql = $emProd->searchConcidencias( $idSeller, $criterio, $attr );
    $products = $dql->getArrayResult();

    if(count($products) > 0) {
      $products = $emProd->reFiltro($products);
    }

    return $this->json(['abort' => false, 'msg' => trim($criterio), 'body' => $products]);
  }

  /** 
   * Recuperamos los datos del cotizador desde el archivo json
  */
  #[Route('security-basic/data-ctc/{token}/{slug}/', methods:['GET', 'POST'])]
  public function getDataContact(
    Request $req, SecurityBasic $lock, String $token, String $slug
  ): Response
  {

    if($lock->isValid($token)) {

      $pathTo = $this->getParameter('dtaCtc') . $slug . '.json';
      if(is_file($pathTo)) {

        if($req->getMethod() == 'GET') {
          $data = file_get_contents($pathTo);
          return new Response($data);
        }

        if($req->getMethod() == 'POST') {

          $content = $req->request->get('data');
          if($content) {
            $content = json_decode($content, true);
            if(array_key_exists('curc', $content)) {
              file_put_contents($pathTo, json_encode($content));
              return $this->json(['abort' => false, 'msg' => 'ok']);
            }
          }

        }
      }
    }

    return $this->json(['abort' => true, 'msg' => 'error']);
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
  #[Route('security-basic/get-inv-ctc/{token}/{waId}/{slug}/', methods:['get'])]
  public function getInvContact(
    SecurityBasic $lock, ShopCoreSystemFileService $sysFile, String $token, String $waId, String $slug
  ): Response
  {
    $data = [];
    if($lock->isValid($token)) {
      $data = $sysFile->getInv($waId, $slug);
    }
    return $this->json($data);
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
    $dta = [];
    if($lock->isValid($token)) {
      $dta = $userEm->cambiarPassword($idCot, $newPass);
    }
    return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => $dta]);
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
