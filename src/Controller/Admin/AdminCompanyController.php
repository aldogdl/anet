<?php

namespace App\Controller\Admin;

use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\PartnerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        $data['isPartner'] = $partnerService->isPartner($safeSlug);

        return $this->json([
            'status' => 'ok',
            'slug' => $safeSlug,
            'expediente' => $data,
        ], Response::HTTP_OK);
    }
}
