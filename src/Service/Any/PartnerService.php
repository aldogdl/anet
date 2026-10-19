<?php

namespace App\Service\Any;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PartnerService
{
    private string $filePath;
    private string $baseDir;

    public function __construct(
        ParameterBagInterface $params,
        ?string $filePath = null
    ) {
        $this->baseDir = $params->has('dtaCtc')
            ? (string) $params->get('dtaCtc')
            : ($params->has('kernel.project_dir') ? (string) $params->get('kernel.project_dir') . '/public_html/ctcs/' : '');
        $this->baseDir = rtrim($this->baseDir, '/\\');

        if ($filePath !== null) {
            $this->filePath = $filePath;
        } else {
            $this->filePath = $this->baseDir . '/partners.json';
        }
    }

    /**
     * Retorna la ruta física del archivo partners.json.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
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

    /**
     * Obtiene la lista actual de socios registrados.
     * 
     * @return string[]
     * @throws \RuntimeException Si el archivo existe pero está corrupto o tiene formato inválido.
     */
    public function getPartners(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException('No se pudo leer el archivo partners.json');
        }

        if (trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['partners']) || !is_array($data['partners'])) {
            throw new \RuntimeException('El archivo partners.json contiene un formato inválido o no posee la estructura {"partners":[...]}');
        }

        $list = [];
        foreach ($data['partners'] as $partner) {
            if (is_string($partner)) {
                $clean = strtolower(trim($partner));
                if ($clean !== '' && !in_array($clean, $list, true)) {
                    $list[] = $clean;
                }
            }
        }

        return $list;
    }

    /**
     * Agrega un slug de socio a partners.json de forma atómica y protegida frente a concurrencia.
     * Es idempotente: si el slug ya es socio, conserva el estado y retorna true.
     * 
     * @param string $slug Slug a agregar.
     * @return bool True si la operación se completó exitosamente.
     * @throws \InvalidArgumentException Si el slug es inválido.
     * @throws \RuntimeException Si ocurre un fallo de bloqueo, lectura de JSON corrupto o reemplazo atómico.
     */
    public function addPartner(string $slug): bool
    {
        $cleanSlug = strtolower(trim($slug));
        if ($cleanSlug === '') {
            throw new \InvalidArgumentException('El slug no puede estar vacío');
        }

        return $this->mutatePartners(function (array &$partners) use ($cleanSlug): bool {
            if (!in_array($cleanSlug, $partners, true)) {
                $partners[] = $cleanSlug;
                return true; // Hubo cambios
            }
            return false; // Idempotente, ya existía
        });
    }

    /**
     * Remueve un slug de socio de partners.json de forma atómica y protegida frente a concurrencia.
     * Es idempotente: si el slug no existía, conserva el estado y retorna true.
     * 
     * @param string $slug Slug a remover.
     * @return bool True si la operación se completó exitosamente.
     * @throws \InvalidArgumentException Si el slug es inválido.
     * @throws \RuntimeException Si ocurre un fallo de bloqueo, lectura de JSON corrupto o reemplazo atómico.
     */
    public function removePartner(string $slug): bool
    {
        $cleanSlug = strtolower(trim($slug));
        if ($cleanSlug === '') {
            throw new \InvalidArgumentException('El slug no puede estar vacío');
        }

        return $this->mutatePartners(function (array &$partners) use ($cleanSlug): bool {
            $initialCount = count($partners);
            $partners = array_values(array_filter($partners, fn($p) => $p !== $cleanSlug));
            return count($partners) !== $initialCount; // True si hubo cambios
        });
    }

    /**
     * Ejecuta una mutación atómica protegida por flock exclusivo sobre partners.json.
     * 
     * @param callable $modifier Callback fn(array &$partners): bool que muta la lista.
     * @return bool True si la operación completó exitosamente.
     * @throws \RuntimeException
     */
    private function mutatePartners(callable $modifier): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            throw new \RuntimeException("El directorio de almacenamiento no existe: $dir");
        }

        // Archivo de bloqueo dedicado para coordinar exclusivamente entre procesos escritores
        $lockFile = $this->filePath . '.lock';
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp === false) {
            throw new \RuntimeException("No se pudo abrir el archivo de bloqueo: $lockFile");
        }

        if (!@flock($lockFp, LOCK_EX)) {
            @fclose($lockFp);
            throw new \RuntimeException("No se pudo adquirir el bloqueo exclusivo sobre $lockFile");
        }

        $tmpFile = null;
        try {
            $partners = [];
            if (file_exists($this->filePath)) {
                $raw = @file_get_contents($this->filePath);
                if ($raw === false) {
                    throw new \RuntimeException("Fallo al leer partners.json bajo bloqueo");
                }

                if (trim($raw) !== '') {
                    $data = json_decode($raw, true);
                    if (!is_array($data) || !isset($data['partners']) || !is_array($data['partners'])) {
                        // REQ 8: Ante JSON inválido o estructura corrupta, FALLA EXPLÍCITAMENTE.
                        // NUNCA sobrescribir con lista vacía.
                        throw new \RuntimeException("El archivo partners.json existe pero contiene JSON corrupto o estructura inválida");
                    }
                    foreach ($data['partners'] as $p) {
                        if (is_string($p)) {
                            $clean = strtolower(trim($p));
                            if ($clean !== '' && !in_array($clean, $partners, true)) {
                                $partners[] = $clean;
                            }
                        }
                    }
                }
            }

            // Ejecutar la mutación
            $hasChanged = $modifier($partners);

            // Reindexar y deduplicar por seguridad
            $partners = array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $partners)))));

            // Si no hubo cambios (idempotencia) y el archivo ya existe, retornar éxito sin reescribir
            if (!$hasChanged && file_exists($this->filePath)) {
                return true;
            }

            $payload = [
                'partners' => $partners,
            ];

            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException("Error al serializar el contenido de partners.json");
            }

            // Escribir en archivo temporal en el mismo directorio para asegurar renombrado atómico en el mismo filesystem
            $tmpFile = tempnam($dir, 'ptrn_tmp_');
            if ($tmpFile === false) {
                throw new \RuntimeException("No se pudo crear archivo temporal para reemplazo atómico");
            }

            if (@file_put_contents($tmpFile, $encoded . "\n", LOCK_EX) === false) {
                throw new \RuntimeException("Error al escribir el archivo temporal $tmpFile");
            }

            // Reemplazo atómico
            if (!@rename($tmpFile, $this->filePath)) {
                @unlink($this->filePath);
                if (!@rename($tmpFile, $this->filePath)) {
                    throw new \RuntimeException("Fallo al renombrar atómicamente el archivo temporal hacia " . $this->filePath);
                }
            }
            $tmpFile = null; // Renombrado exitoso

            return true;
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            if (is_resource($lockFp)) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }
}
