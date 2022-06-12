<?php

namespace App\Controller\SCP;

use App\Repository\NG2ContactosRepository;
use App\Repository\CampaingsRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SharedGetController extends AbstractController
{

  /***/
  #[Route('scp/get-all-contactos-by/{tipo}/', methods: ['get'])]
  public function getAllContactsBy(
    NG2ContactosRepository $contactsEm,
    string $tipo
  ): Response {
    $dql = $contactsEm->getAllContactsBy($tipo);
    return $this->json([
      'abort' => false, 'msg' => 'ok',
      'body' => $dql->getScalarResult()
    ]);
  }

  /***/
  #[Route('scp/delete-contacto/{idContac}/', methods: ['get'])]
  public function deleteContacto(NG2ContactosRepository $contactsEm, int $idContac): Response {
    $result = $contactsEm->borrarContactoById($idContac);
    return $this->json($result);
  }

  /**
   * Buscamos el id de la campaña que contenga la palabra clave.
   */
  #[Route('scp/get-id-campaing-by-slug/{slug}/', methods:['get'])]
  public function getIdCampaingBySlug(CampaingsRepository $campEm, string $slug): Response
  {
    $dql = $campEm->getIdCampaingBySlug($slug);
    return $this->json([
      'abort'=>false, 'msg' => 'ok',
      'body' => $dql->getScalarResult()
    ]);
  }
}
