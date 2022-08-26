<?php

namespace App\Controller\SCP;

use App\Repository\NG2ContactosRepository;
use App\Repository\CampaingsRepository;
use App\Repository\OrdenRespsRepository;
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

  /** Recuperamos las respuestas y colocamos el nuevo statu a las piezas */
	#[Route('scp/get-resps-by-pzas/{ids}/', methods:['get'])]
	public function getRespuestaByPiezas(
    OrdenRespsRepository $rpsEm, $ids
  ): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $partes = explode(',', $ids);

    // Recuperamos las respuestas
		$dql = $rpsEm->getRespsByIdPzas($partes);
    $r['body'] = $dql->getScalarResult();
		return $this->json($r);
	}

  /** Recuperamos la respuesta por su ID */
	#[Route('scp/get-resp-by-ids/{ids}/', methods:['get'])]
	public function getRespuestaByIds( OrdenRespsRepository $rpsEm, $ids ): Response
	{
    $r = ['abort' => false, 'msg' => 'ok', 'body' => []];
    $partes = explode(',', $ids);

    // Recuperamos las respuestas
		$dql = $rpsEm->getRespuestaByIds($partes);
    $r['body'] = $dql->getArrayResult();
		return $this->json($r);
	}

}
