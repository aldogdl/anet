<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppPresenceService
{
    private string $storageDir;

    public function __construct(ParameterBagInterface $params)
    {
        $baseDir = $params->has('presence')
            ? (string)$params->get('presence')
            : (string)$params->get('phtml') . '/presence';

        $this->storageDir = rtrim($baseDir, '/\\');
    }

    /**
     * Registra una apertura de la app en un archivo JSON {slug}-{waId}.json
     *
     * @param string $slug
     * @param string $waId
     * @param string $dev
     * @return array{abort: bool, body: string}
     */
    public function recordOpen(string $slug, string $waId, string $dev): array
    {
        // 1. Sanitizar slug y waId para evitar traversal de rutas o caracteres inválidos
        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        $safeWaId = preg_replace('/[^a-zA-Z0-9_-]/', '', $waId);

        if (empty($safeSlug) || empty($safeWaId)) {
            return [
                'abort' => true,
                'body' => 'Identificadores inválidos después de sanitización',
            ];
        }

        // Asegurar que el directorio de destino exista
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }

        $filePath = $this->storageDir . '/' . $safeSlug . '-' . $safeWaId . '.json';

        // 2. Abrir archivo en modo 'c+' (crea si no existe, no trunca al abrir)
        $fp = @fopen($filePath, 'c+');
        if (!$fp) {
            return [
                'abort' => true,
                'body' => 'No se pudo abrir el archivo de presencia',
            ];
        }

        try {
            // Bloqueo exclusivo que cubre todo el ciclo read-modify-write
            if (!flock($fp, LOCK_EX)) {
                return [
                    'abort' => true,
                    'body' => 'No se pudo obtener el bloqueo exclusivo del archivo',
                ];
            }

            // 3. Leer contenido existente
            $stat = fstat($fp);
            $size = $stat['size'] ?? 0;
            $entries = [];

            if ($size > 0) {
                rewind($fp);
                $rawContent = fread($fp, $size);
                if ($rawContent !== false && trim($rawContent) !== '') {
                    $decoded = json_decode($rawContent, true);
                    if (is_array($decoded)) {
                        $entries = $decoded;
                    }
                }
            }

            // 4. Crear nueva entrada con formato ISO-8601 e insertarla al inicio
            $newEntry = [
                'at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'dev' => $dev,
            ];
            array_unshift($entries, $newEntry);

            // 5. Conservar únicamente las últimas 15 entradas
            $entries = array_slice($entries, 0, 15);

            // 6. Truncar, escribir nuevo JSON y forzar flush a disco
            $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, $json);
            fflush($fp);

            // 7. Liberar bloqueo
            flock($fp, LOCK_UN);

            return [
                'abort' => false,
                'body' => 'ok',
            ];
        } finally {
            fclose($fp);
        }
    }
}
