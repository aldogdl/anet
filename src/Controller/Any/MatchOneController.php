<?php

namespace App\Controller\Any;

use App\Repository\ItemPubRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/match-one')]
class MatchOneController extends AbstractController
{
	/**
	 * Endpoint de búsqueda masiva ligera para MatchOne
	 */
	#[Route('/search', methods: ['POST'])]
	public function search(Request $req, ItemPubRepository $repo): Response
	{
		$data = $req->getContent();
		if (!$data) {
			return $this->json(['abort' => true, 'body' => 'X No se ha enviado el body'], Response::HTTP_BAD_REQUEST);
		}

		$data = json_decode($data, true);
		if (!is_array($data)) {
			return $this->json(['abort' => true, 'body' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
		}

		// Si se solicita allowSelf o búsqueda abierta, no filtrar por waId != :waId
		if (isset($data['allowSelf']) && $data['allowSelf'] === true) {
			$data['waId'] = '';
		}

		$res = $repo->matchOne($data);
		return $this->json(['abort' => false, 'body' => $res]);
	}

	/**
	 * Endpoint diferido para consultar los detalles completos de un ítem por ID
	 */
	#[Route('/details/{id}', methods: ['GET'])]
	public function details(int $id, ItemPubRepository $repo): Response
	{
		if ($id <= 0) {
			return $this->json(['abort' => true, 'body' => 'ID inválido'], Response::HTTP_BAD_REQUEST);
		}

		$itemDetails = $repo->getIfExistPubByIdToArray($id);
		if (empty($itemDetails)) {
			return $this->json(['abort' => true, 'body' => 'Ítem no encontrado'], Response::HTTP_NOT_FOUND);
		}

		$item = $itemDetails[0] ?? $itemDetails;
		if (isset($item['extras']) && is_string($item['extras'])) {
			$item['extras'] = json_decode($item['extras'], true) ?? [];
		}

		return $this->json(['abort' => false, 'body' => $item]);
	}
}

