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
     * Lista todos los slugs de empresas disponibles en ctcs.
     * Retorna únicamente nombres de archivo sin extensión .json.
     * Excluye partners.json y archivos auxiliares/índices.
     * No abre el contenido de cada archivo.
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

        $excluded = [
            'partners.json',
            'index_dta_ctc.json',
            'log',
            '.',
            '..',
        ];

        $companies = [];
        $files = scandir($folder);
        if ($files !== false) {
            foreach ($files as $file) {
                if (in_array(strtolower($file), $excluded, true)) {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $slug = pathinfo($file, PATHINFO_FILENAME);
                    if ($slug !== '') {
                        $companies[] = $slug;
                    }
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
