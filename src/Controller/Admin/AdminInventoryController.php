<?php

namespace App\Controller\Admin;

use App\Repository\ItemPubRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Security\AdminJwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controlador administrativo de solo lectura para el módulo de Inventario.
 *
 * Protegido exclusivamente mediante AdminJwtService (JWT RS256 de Auth con RBAC inventory.read)
 * y fallback server-to-server mediante ANY_TOKEN.
 */
#[Route('/admin-api/inventory')]
class AdminInventoryController extends AbstractController
{
    private AdminJwtService $jwtService;
    private ItemPubRepository $itemPubRepository;

    public function __construct(AdminJwtService $jwtService, ItemPubRepository $itemPubRepository)
    {
        $this->jwtService = $jwtService;
        $this->itemPubRepository = $itemPubRepository;
    }

    /**
     * Lista publicaciones con paginación real, filtros por empresa, estado, búsqueda y ordenamiento.
     * Proyección estricta sin waId ni costo.
     */
    #[Route('/publications', methods: ['GET'])]
    public function listPublications(Request $request): JsonResponse
    {
        try {
            $this->jwtService->authenticateRequest($request, 'inventory.read');
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $page = (int)$request->query->get('page', 1);
        $limit = (int)$request->query->get('limit', 50);
        $slug = $request->query->get('slug');
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $sortBy = (string)$request->query->get('sortBy', 'updatedAt');
        $sortDir = (string)$request->query->get('sortDir', 'DESC');

        $filters = [];
        if ($slug !== null && trim($slug) !== '' && trim($slug) !== 'Todas las empresas') {
            $filters['slug'] = trim($slug);
        }
        if ($status !== null && trim($status) !== '') {
            $filters['status'] = trim($status);
        }
        if ($search !== null && trim($search) !== '') {
            $filters['search'] = trim($search);
        }

        try {
            $result = $this->itemPubRepository->findAdminPublications(
                $filters,
                $page,
                $limit,
                $sortBy,
                $sortDir
            );

            return new JsonResponse([
                'abort' => false,
                'body' => $result,
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'internal_error',
                'message' => 'Error al consultar publicaciones de inventario.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Lista agregada de empresas con publicaciones y conteos reales (activas e inactivas) sin N+1.
     */
    #[Route('/companies', methods: ['GET'])]
    public function listCompaniesWithInventory(Request $request, Fsys $fsys): JsonResponse
    {
        try {
            $this->jwtService->authenticateRequest($request, 'inventory.read');
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        try {
            $companies = $this->itemPubRepository->getAdminCompaniesWithInventory();

            // Enriquecer con nombre legible de empresa si existe el expediente ctcs/<slug>.json
            $enriched = array_map(function(array $item) use ($fsys) {
                $slug = $item['slug'];
                $companyName = $slug;

                try {
                    $dossier = $fsys->get($this->getParameter(AnyPath::$DTACTC), $slug . '.json');
                    if (is_array($dossier) && !empty($dossier['empresa'])) {
                        $companyName = (string)$dossier['empresa'];
                    }
                } catch (\Throwable) {
                    // Silenciosamente usar slug como nombre
                }

                $item['name'] = $companyName;
                return $item;
            }, $companies);

            return new JsonResponse([
                'abort' => false,
                'body' => [
                    'companies' => $enriched,
                    'totalCompanies' => count($enriched),
                ],
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'internal_error',
                'message' => 'Error al consultar empresas con inventario.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retorna las 8 métricas reales de inventario (globales o por empresa).
     * Aplica reglas confirmadas de stt y diferenciación de tupla mrk_id/mdl_id.
     */
    #[Route('/metrics', methods: ['GET'])]
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $this->jwtService->authenticateRequest($request, 'inventory.read');
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $slug = $request->query->get('slug');
        if ($slug !== null) {
            $slug = trim($slug);
            if ($slug === '' || $slug === 'Todas las empresas') {
                $slug = null;
            }
        }

        try {
            $metrics = $this->itemPubRepository->getAdminInventoryMetrics($slug);

            return new JsonResponse([
                'abort' => false,
                'msg' => 'Métricas de inventario calculadas exitosamente',
                'body' => [
                    'metrics' => $metrics,
                    'scope' => $slug ?? 'all',
                ],
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'internal_error',
                'message' => 'Error al calcular métricas de inventario: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

