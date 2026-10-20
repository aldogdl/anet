<?php

namespace App\Controller\Admin;

use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\PartnerService;
use App\Service\Any\ExpedienteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin-api')]
class AdminCompanyController extends AbstractController
{
    /**
     * Valida estructuralmente si un archivo JSON corresponde a un expediente empresarial legítimo.
     *
     * Criterio estructural general:
     * 1. Archivo existente, legible y no directorio.
     * 2. Exclusión de prefijos y patrones de sistema/auxiliares (any_*, tpub*, rep_*, exp_*, saf_*, prueba_*, anytest, cot\d+, etc.).
     * 3. El contenido JSON debe decodificar como array asociativo (no lista indexada, no escalar).
     * 4. Identidad empresarial canónica: presencia de 'empresa' no vacía y 'slug' coincidente con el nombre base del archivo.
     * 5. Estructura de colaboradores: presencia de 'colabs' no vacío con al menos un colaborador con 'waId'.
     *
     * Conserva expedientes legítimos aunque carezcan de campos opcionales (logo, nif, categoría, dirección, etc.).
     */
    public static function isValidCompanyDossier(string $filePath, string $filename): bool
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            return false;
        }

        $baseSlug = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        if ($baseSlug === '') {
            return false;
        }

        // Criterio estructural de nomenclatura no empresarial / auxiliar / sistema
        if (preg_match('/^(any_|tpub|rep_|exp_|saf_|prueba_|anytest$|cot\d+$|partners$|versions$|index_)/i', $baseSlug)) {
            return false;
        }

        $content = @file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data)) {
            return false;
        }

        // Descartar arrays indexados numéricamente (listas puras como any_block, any_ref, piezas)
        if (array_keys($data) === range(0, count($data) - 1)) {
            return false;
        }

        // Validar identidad empresarial: empresa y slug no vacíos
        if (empty($data['empresa']) || !is_string($data['empresa']) || trim($data['empresa']) === '') {
            return false;
        }
        if (empty($data['slug']) || !is_string($data['slug']) || trim($data['slug']) === '') {
            return false;
        }

        // Coherencia de slug
        if (strtolower(trim($data['slug'])) !== $baseSlug) {
            return false;
        }

        // Validar lista de colaboradores con al menos un colaborador con waId
        if (!isset($data['colabs']) || !is_array($data['colabs']) || empty($data['colabs'])) {
            return false;
        }

        $hasColabWithWa = false;
        foreach ($data['colabs'] as $colab) {
            if (is_array($colab) && !empty($colab['waId']) && trim((string)$colab['waId']) !== '') {
                $hasColabWithWa = true;
                break;
            }
        }

        return $hasColabWithWa;
    }

    /**
     * Lista todos los slugs de empresas disponibles en ctcs que cumplan el criterio estructural.
     * Retorna únicamente nombres de archivo sin extensión .json.
     * Excluye archivos auxiliares, de prueba y tablas del sistema.
     */
    #[Route('/companies', methods: ['GET'])]
    public function listCompanies(): JsonResponse
    {
        $folder = (string) $this->getParameter('dtaCtc');
        if (!is_dir($folder)) {
            return $this->json([
                'status' => 'ok',
                'companies' => [],
                'total' => 0,
            ]);
        }

        $companies = [];
        $files = scandir($folder);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = $folder . DIRECTORY_SEPARATOR . $file;
                if (self::isValidCompanyDossier($filePath, $file)) {
                    $companies[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }
        }

        sort($companies, SORT_STRING | SORT_FLAG_CASE);

        return $this->json([
            'status' => 'ok',
            'companies' => $companies,
            'total' => count($companies),
        ]);
    }

    /**
     * Obtiene el expediente sanitizado de una empresa por slug.
     * Sanitiza colaboradores removiendo pass / contraseñas.
     * Incluye condición de socio (isPartner).
     */
    #[Route('/companies/{slug}', methods: ['GET'])]
    public function getCompany(string $slug, Fsys $fsys, PartnerService $partnerService): JsonResponse
    {
        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($slug)));
        if (empty($safeSlug)) {
            return $this->json([
                'error' => 'slug_invalid',
                'message' => 'Slug no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $folder = (string) $this->getParameter('dtaCtc');
        $filePath = $folder . DIRECTORY_SEPARATOR . $safeSlug . '.json';
        if (!self::isValidCompanyDossier($filePath, $safeSlug . '.json')) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Expediente empresarial no encontrado o no válido',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $fsys->get(AnyPath::$DTACTC, $safeSlug . '.json');
        if (empty($data) || !is_array($data)) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Expediente no encontrado',
            ], Response::HTTP_NOT_FOUND);
        }

        // Sanitizar colaboradores: remover contraseñas y secretos
        if (isset($data['colabs']) && is_array($data['colabs'])) {
            foreach ($data['colabs'] as &$colab) {
                if (is_array($colab)) {
                    unset($colab['pass']);
                }
            }
            unset($colab);
        }

        $isPartner = $partnerService->isPartner($safeSlug);
        $data['isPartner'] = $isPartner;

        return $this->json([
            'status' => 'ok',
            'slug' => $safeSlug,
            'isPartner' => $isPartner,
            'expediente' => $data,
        ], Response::HTTP_OK);
    }

    /**
     * Actualiza los datos comerciales de una empresa bajo lista blanca estricta.
     * Requiere autorización administrativa.
     */
    #[Route('/companies/{slug}/commercial-info', methods: ['PATCH', 'PUT'])]
    public function updateCommercialInfo(
        string $slug,
        Request $request,
        ExpedienteManager $expManager,
        PartnerService $partnerService
    ): JsonResponse {
        if (!$this->isAuthorizedAdmin($request)) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Acceso administrativo no autorizado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($slug)));
        if (empty($safeSlug)) {
            return $this->json([
                'error' => 'slug_invalid',
                'message' => 'Slug no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'Cuerpo de solicitud JSON no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Filtro estricto de lista blanca: solo campos comerciales
        $allowedKeys = ['empresa', 'localidad', 'address', 'colonia', 'categoria', 'prestige'];
        $filtered = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $filtered[$key] = $payload[$key];
            }
        }

        if (empty($filtered)) {
            return $this->json([
                'error' => 'empty_payload',
                'message' => 'No se proporcionó ningún campo comercial modificable.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $updatedExp = $expManager->updateCommercialInfoByAdmin($safeSlug, $filtered);

            // Sanitizar colaboradores al retornar
            if (isset($updatedExp['colabs']) && is_array($updatedExp['colabs'])) {
                foreach ($updatedExp['colabs'] as &$colab) {
                    if (is_array($colab)) {
                        unset($colab['pass']);
                    }
                }
                unset($colab);
            }

            $isPartner = $partnerService->isPartner($safeSlug);
            $updatedExp['isPartner'] = $isPartner;

            return $this->json([
                'status' => 'ok',
                'slug' => $safeSlug,
                'isPartner' => $isPartner,
                'expediente' => $updatedExp,
            ], Response::HTTP_OK);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'update_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sincroniza unilateralmente la suscripción comercial (plan, nif) con control de revisión monotónica.
     * Requiere autorización administrativa con ANY_TOKEN.
     */
    #[Route('/companies/{slug}/subscription-sync', methods: ['PATCH', 'PUT'])]
    public function updateSubscriptionSync(
        string $slug,
        Request $request,
        ExpedienteManager $expManager,
        PartnerService $partnerService
    ): JsonResponse {
        if (!$this->isAuthorizedAdmin($request)) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Acceso administrativo no autorizado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($slug)));
        if (empty($safeSlug)) {
            return $this->json([
                'error' => 'slug_invalid',
                'message' => 'Slug no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'Cuerpo de solicitud JSON no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $plan = isset($payload['plan']) ? strtolower(trim((string)$payload['plan'])) : '';
        $nif = isset($payload['nif']) && is_numeric($payload['nif']) ? (int)$payload['nif'] : 0;
        $commercialRevision = isset($payload['commercialRevision']) && is_numeric($payload['commercialRevision'])
            ? (int)$payload['commercialRevision']
            : 0;

        if (empty($plan) || $nif <= 0 || $commercialRevision <= 0) {
            return $this->json([
                'error' => 'invalid_parameters',
                'message' => 'Los campos plan (canónico), nif (>0) y commercialRevision (>0) son requeridos.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $expManager->updateSubscriptionByAdmin($safeSlug, $plan, $nif, $commercialRevision);

            $exp = $result['expediente'] ?? [];
            if (isset($exp['colabs']) && is_array($exp['colabs'])) {
                foreach ($exp['colabs'] as &$colab) {
                    if (is_array($colab)) {
                        unset($colab['pass']);
                    }
                }
                unset($colab);
            }
            $result['expediente'] = $exp;
            $result['isPartner'] = $partnerService->isPartner($safeSlug);

            return $this->json($result, Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'bad_request',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'sync_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Valida si la solicitud contiene una credencial administrativa confiable.
     * Soporta 'any-token', 'x-admin-token' o 'Authorization: Bearer <token>'.
     */
    private function isAuthorizedAdmin(Request $request): bool
    {
        $token = $request->headers->get('any-token')
            ?? $request->headers->get('x-admin-token')
            ?? $request->headers->get('admin-token');

        if ($token === null) {
            $authHeader = $request->headers->get('Authorization') ?? '';
            if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $expected = $this->getParameter('anyToken');
        if (!empty($expected) && hash_equals((string)$expected, trim($token))) {
            return true;
        }

        return false;
    }

    /**
     * Lista los socios registrados en partners.json.
     * Requiere autorización administrativa.
     */
    #[Route('/partners', methods: ['GET'])]
    public function listPartners(Request $request, PartnerService $partnerService): JsonResponse
    {
        if (!$this->isAuthorizedAdmin($request)) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Acceso administrativo no autorizado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $partners = $partnerService->getPartners();
            return $this->json([
                'status' => 'ok',
                'partners' => $partners,
                'total' => count($partners),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'server_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Agrega un slug a partners.json de forma atómica e idempotente.
     * Valida existencia de expediente de empresa previo a la asignación.
     * Requiere autorización administrativa.
     */
    #[Route('/partners/{slug}', methods: ['POST', 'PUT'])]
    public function addPartner(string $slug, Request $request, PartnerService $partnerService): JsonResponse
    {
        if (!$this->isAuthorizedAdmin($request)) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Acceso administrativo no autorizado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($slug)));
        if (empty($safeSlug)) {
            return $this->json([
                'error' => 'slug_invalid',
                'message' => 'Slug de socio no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validar que la empresa exista y sea un expediente empresarial legítimo
        $folder = (string) $this->getParameter('dtaCtc');
        $filePath = $folder . DIRECTORY_SEPARATOR . $safeSlug . '.json';
        if (!self::isValidCompanyDossier($filePath, $safeSlug . '.json')) {
            return $this->json([
                'error' => 'company_not_found',
                'message' => 'No se puede registrar como socio: la empresa no existe o no posee un expediente válido',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $partnerService->addPartner($safeSlug);

            return $this->json([
                'status' => 'ok',
                'slug' => $safeSlug,
                'isPartner' => true,
                'message' => 'Socio registrado correctamente',
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'partner_update_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remueve un slug de partners.json de forma atómica e idempotente.
     * Requiere autorización administrativa.
     */
    #[Route('/partners/{slug}', methods: ['DELETE'])]
    public function removePartner(string $slug, Request $request, PartnerService $partnerService): JsonResponse
    {
        if (!$this->isAuthorizedAdmin($request)) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Acceso administrativo no autorizado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($slug)));
        if (empty($safeSlug)) {
            return $this->json([
                'error' => 'slug_invalid',
                'message' => 'Slug de socio no válido',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $partnerService->removePartner($safeSlug);

            return $this->json([
                'status' => 'ok',
                'slug' => $safeSlug,
                'isPartner' => false,
                'message' => 'Socio removido correctamente',
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'partner_update_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
