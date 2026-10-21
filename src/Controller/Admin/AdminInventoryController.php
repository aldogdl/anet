<?php

namespace App\Controller\Admin;

use App\Repository\ItemPubRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\InventoryTemporalImportService;
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
            $context = $this->jwtService->authenticateRequest($request, 'inventory.read');
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $canViewCosto = $this->jwtService->hasPermission($context->getRoleAdmin(), 'inventory.read');

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
                $sortDir,
                $canViewCosto
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

    /**
     * Exporta temporalmente el inventario completo de una empresa a CSV en el servidor.
     * 
     * Requisitos:
     * - Restringido exclusivamente a superadministradores (inventory.write y role superadmin).
     * - Slug obligatorio y estrictamente validado (no admite exportación global ni vacío).
     * - Genera el archivo en /public_html/inv/admin_export/ protegido por .htaccess.
     * - Retorna identificador, total y tamaño sin proveer URL de descarga directa.
     */
    #[Route('/export-temporal', methods: ['POST', 'OPTIONS'])]
    public function exportTemporal(Request $request, \App\Service\InventoryImportService $importService): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        try {
            $context = $this->jwtService->authenticateRequest($request, 'inventory.write');
            if ($context->getRoleAdmin() !== 'superadmin') {
                return new JsonResponse([
                    'abort' => true,
                    'error' => 'forbidden',
                    'message' => 'Acceso denegado: se requieren facultades de superadministrador para exportar inventario.',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $content = $request->getContent();
        $payload = [];
        if (!empty($content)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $rawSlug = $payload['slug'] ?? $request->request->get('slug') ?? $request->query->get('slug');

        if ($rawSlug === null || !is_string($rawSlug)) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'missing_slug',
                'message' => 'El parámetro slug es obligatorio.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $slug = trim($rawSlug);
        if ($slug === '' || $slug === 'Todas las empresas') {
            return new JsonResponse([
                'abort' => true,
                'error' => 'invalid_slug',
                'message' => 'Debe especificarse una empresa concreta para la exportación temporal.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'invalid_slug_format',
                'message' => 'El formato del identificador de empresa es inválido.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $importService->exportAdminTemporalCsv($slug);

            return new JsonResponse([
                'abort' => false,
                'msg' => 'Exportación temporal generada exitosamente en el servidor.',
                'body' => $result,
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'invalid_parameter',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'export_error',
                'message' => 'Error al generar la exportación temporal de inventario: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Lista los archivos CSV temporales de la empresa seleccionada en /public_html/inv/admin_export/.
     */
    #[Route('/temporal-files', methods: ['GET', 'OPTIONS'])]
    public function listTemporalFiles(Request $request, InventoryTemporalImportService $importService): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Requested-With',
            ]);
        }

        try {
            $context = $this->jwtService->authenticateRequest($request, 'inventory.read');
            if ($context->getRoleAdmin() !== 'superadmin') {
                return new JsonResponse([
                    'abort' => true,
                    'error' => 'forbidden',
                    'message' => 'Acceso denegado: se requieren facultades de superadministrador.',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $rawSlug = $request->query->get('slug');
        if ($rawSlug === null || !is_string($rawSlug) || trim($rawSlug) === '') {
            return new JsonResponse([
                'abort' => true,
                'error' => 'missing_slug',
                'message' => 'El parámetro slug es obligatorio.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $files = $importService->listTemporalFiles($rawSlug);
            return new JsonResponse([
                'abort' => false,
                'body' => [
                    'slug' => trim($rawSlug),
                    'files' => $files,
                ],
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'invalid_parameter',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'list_error',
                'message' => 'Error al listar archivos temporales: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Prevalida en seco un archivo CSV temporal sin escribir en MySQL.
     */
    #[Route('/prevalidate-temporal', methods: ['POST', 'OPTIONS'])]
    public function prevalidateTemporal(Request $request, InventoryTemporalImportService $importService): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Requested-With',
            ]);
        }

        try {
            $context = $this->jwtService->authenticateRequest($request, 'inventory.write');
            if ($context->getRoleAdmin() !== 'superadmin') {
                return new JsonResponse([
                    'abort' => true,
                    'error' => 'forbidden',
                    'message' => 'Acceso denegado: se requieren facultades de superadministrador.',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $rawSlug = $payload['slug'] ?? null;
        $rawFilename = $payload['filename'] ?? null;
        $resolutions = $payload['resolutions'] ?? [];

        if (empty($rawSlug) || !is_string($rawSlug) || empty($rawFilename) || !is_string($rawFilename)) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'missing_parameters',
                'message' => 'Los parámetros slug y filename son obligatorios.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $importService->prevalidateTemporalCsv(trim($rawSlug), trim($rawFilename), is_array($resolutions) ? $resolutions : []);
            return new JsonResponse([
                'abort' => false,
                'msg' => 'Prevalidación ejecutada con éxito (modo solo lectura).',
                'body' => $result,
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'invalid_parameter',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'prevalidation_error',
                'message' => 'Error durante la prevalidación: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Genera un IKU único garantizado a nivel global utilizando GeneratorIku.
     */
    #[Route('/generate-iku', methods: ['POST', 'OPTIONS'])]
    public function generateGuaranteedIku(Request $request, InventoryTemporalImportService $importService): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Requested-With',
            ]);
        }

        try {
            $context = $this->jwtService->authenticateRequest($request, 'inventory.write');
            if ($context->getRoleAdmin() !== 'superadmin') {
                return new JsonResponse([
                    'abort' => true,
                    'error' => 'forbidden',
                    'message' => 'Acceso denegado: se requieren facultades de superadministrador.',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $excludeIkus = $payload['excludeIkus'] ?? [];

        try {
            $newIku = $importService->generateGuaranteedUniqueIku(is_array($excludeIkus) ? $excludeIkus : []);
            return new JsonResponse([
                'abort' => false,
                'body' => [
                    'iku' => $newIku,
                ],
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'generate_iku_error',
                'message' => 'Error al generar IKU: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resuelve en masa todas las colisiones que admitan 'generate_new_iku'.
     */
    #[Route('/resolve-all-conflicts', methods: ['POST', 'OPTIONS'])]
    public function resolveAllConflicts(Request $request, InventoryTemporalImportService $importService): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin, X-Requested-With',
            ]);
        }

        try {
            $context = $this->jwtService->authenticateRequest($request, 'inventory.write');
            if ($context->getRoleAdmin() !== 'superadmin') {
                return new JsonResponse([
                    'abort' => true,
                    'error' => 'forbidden',
                    'message' => 'Acceso denegado: se requieren facultades de superadministrador.',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->jwtService->handleAuthError($e);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $rawSlug = $payload['slug'] ?? null;
        $rawFilename = $payload['filename'] ?? null;
        $currentResolutions = $payload['resolutions'] ?? [];

        if (empty($rawSlug) || !is_string($rawSlug) || empty($rawFilename) || !is_string($rawFilename)) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'missing_parameters',
                'message' => 'Los parámetros slug y filename son obligatorios.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $resolutions = $importService->resolveAllConflicts(
                trim($rawSlug),
                trim($rawFilename),
                is_array($currentResolutions) ? $currentResolutions : []
            );

            return new JsonResponse([
                'abort' => false,
                'body' => [
                    'resolutions' => $resolutions,
                    'count' => count($resolutions),
                ],
            ], Response::HTTP_OK, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'abort' => true,
                'error' => 'resolve_all_error',
                'message' => 'Error al resolver colisiones: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}


