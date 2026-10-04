<?php

namespace App\Service\Any;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PartnerService
{
    private string $filePath;

    public function __construct(
        ParameterBagInterface $params,
        ?string $filePath = null
    ) {
        if ($filePath !== null) {
            $this->filePath = $filePath;
        } else {
            $baseDir = $params->has('dtaCtc')
                ? (string) $params->get('dtaCtc')
                : ($params->has('kernel.project_dir') ? (string) $params->get('kernel.project_dir') . '/public_html/ctcs/' : '');
            $this->filePath = rtrim($baseDir, '/\\') . '/partners.json';
        }
    }

    /**
     * Determina si el slug proporcionado pertenece a un socio registrado.
     * 
     * Reglas:
     * - Normaliza el slug con trim y strtolower.
     * - Si el archivo no existe, está corrupto o el slug no aparece -> false.
     * - Fallback seguro: nunca otorga privilegios ante errores o excepciones.
     */
    public function isPartner(?string $slug): bool
    {
        if ($slug === null) {
            return false;
        }

        $cleanSlug = strtolower(trim($slug));
        if ($cleanSlug === '') {
            return false;
        }

        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            return false;
        }

        try {
            $raw = file_get_contents($this->filePath);
            if ($raw === false || trim($raw) === '') {
                return false;
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['partners']) || !is_array($data['partners'])) {
                return false;
            }

            foreach ($data['partners'] as $partner) {
                if (is_string($partner) && strtolower(trim($partner)) === $cleanSlug) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
