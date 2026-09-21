<?php

namespace App\Controller\Any;

use App\Service\Any\RemateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller delgado para los endpoints REST del dominio Remates.
 * Delega la validación de propiedad y lógica de negocio a RemateService.
 */
#[Route('/any-remate')]
class RemateController extends AbstractController
{

    /**
     * Crear o actualizar (upsert) un Remate
     */
    #[Route('/pub', methods: ['POST'])]
    public function pub(Request $req, RemateService $service): Response
    {
        $data = $this->parseJsonBody($req);
        if ($data === null) {
            return $this->json(['abort' => true, 'body' => 'JSON inválido o cuerpo vacío'], Response::HTTP_BAD_REQUEST);
        }

        $res = $service->upsert($data);
        $code = $res['code'] ?? Response::HTTP_OK;
        unset($res['code']);

        return $this->json($res, $code);
    }

    /**
     * Actualizar únicamente precioRemate y notas/detalles
     */
    #[Route('/update', methods: ['POST', 'PATCH'])]
    public function update(Request $req, RemateService $service): Response
    {
        $data = $this->parseJsonBody($req);
        if ($data === null) {
            return $this->json(['abort' => true, 'body' => 'JSON inválido o cuerpo vacío'], Response::HTTP_BAD_REQUEST);
        }

        $res = $service->updatePrecioYNotas($data);
        $code = $res['code'] ?? Response::HTTP_OK;
        unset($res['code']);

        return $this->json($res, $code);
    }

    /**
     * Consultar status de disponibilidad de un remate por remateId
     * Respuesta mínima: { "remateId": "...", "status": <int> }
     */
    #[Route('/status', methods: ['GET'])]
    public function getStatus(Request $req, RemateService $service): Response
    {
        $remateId = trim((string)$req->query->get('remateId', ''));
        $res = $service->checkStatus($remateId);

        return $this->json($res, Response::HTTP_OK);
    }

    /**
     * Actualizar únicamente el status comercial del remate
     */
    #[Route('/status', methods: ['POST', 'PATCH'])]
    public function status(Request $req, RemateService $service): Response
    {
        $data = $this->parseJsonBody($req);
        if ($data === null) {
            return $this->json(['abort' => true, 'body' => 'JSON inválido o cuerpo vacío'], Response::HTTP_BAD_REQUEST);
        }

        $res = $service->updateStatus($data);
        $code = $res['code'] ?? Response::HTTP_OK;
        unset($res['code']);

        return $this->json($res, $code);
    }

    /**
     * Eliminar / Cancelar remate
     */
    #[Route('/delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $req, RemateService $service): Response
    {
        $data = [];
        if ($req->getMethod() === 'DELETE') {
            $data = [
                'iku' => $req->query->get('iku', ''),
                'ownerSlug' => $req->query->get('ownerSlug') ?? $req->query->get('slug', ''),
            ];
        } else {
            $data = $this->parseJsonBody($req) ?? [];
        }

        $res = $service->delete($data);
        $code = $res['code'] ?? Response::HTTP_OK;
        unset($res['code']);

        return $this->json($res, $code);
    }

    /**
     * Listar remates propios con paginado
     */
    #[Route('/list', methods: ['GET'])]
    public function list(Request $req, RemateService $service): Response
    {
        $slug = trim((string)($req->query->get('slug') ?? $req->query->get('ownerSlug', '')));
        $waId = trim((string)($req->query->get('waId') ?? $req->query->get('ownerWaId', '')));
        $page = max(1, (int)$req->query->get('page', 1));
        $limit = max(1, min(100, (int)$req->query->get('limit', 50)));

        $res = $service->listMisRemates($slug, $waId, $page, $limit);
        $code = $res['code'] ?? Response::HTTP_OK;
        unset($res['code']);

        return $this->json($res, $code);
    }

    /**
     * Helper para parsear JSON de forma segura
     */
    private function parseJsonBody(Request $req): ?array
    {
        $raw = $req->getContent();
        if (!$raw) {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
