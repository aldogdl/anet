<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Servicio administrativo para la eliminación segura de inventario completo por empresa.
 *
 * REGLAS DE SEGURIDAD ABSOLUTAS:
 * 1. Restringido exclusivamente a superadmin y protegido contra concurrencia.
 * 2. Previsualización obligatoria previa a cualquier mutación física o SQL.
 * 3. Detección y conservación estricta de fotografías compartidas por otras publicaciones o empresas.
 * 4. Eliminación archivo por archivo exclusivo; jamás borrado recursivo por IKU.
 * 5. Cuarentena con manifiesto persistente y restitución automática ante fallo en transacción SQL.
 * 6. Preservación íntegra de ng1_empresas, ng2_contactos, sys_com, sync_ml y mmentity.
 */
class InventoryCompanyDeletionService
{
    private Connection $conn;
    private string $invExp;
    private Filesystem $filesystem;

    public function __construct(Connection $conn, string $invExp)
    {
        $this->conn = $conn;
        $this->invExp = rtrim($invExp, '/\\');
        $this->filesystem = new Filesystem();
    }

    /**
     * Obtiene la ruta física autorizada para las imágenes de una empresa.
     */
    public function getCompanyImagesDir(string $slug): string
    {
        return $this->invExp . '/images/' . $slug;
    }

    /**
     * Obtiene el directorio base de cuarentena temporal.
     */
    public function getQuarantineBaseDir(): string
    {
        $dir = $this->invExp . '/quarantine';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Valida la sintaxis del slug canónico.
     */
    public function validateSlug(string $slug): string
    {
        $clean = trim($slug);
        if ($clean === '' || $clean === 'Todas las empresas' || !preg_match('/^[a-zA-Z0-9_-]+$/', $clean)) {
            throw new \InvalidArgumentException('El parámetro slug es obligatorio y debe ser alfanumérico.');
        }
        return $clean;
    }

    /**
     * Obtiene la ruta física del archivo de barrera de escritura para una empresa.
     */
    public function getWriteBarrierPath(string $slug): string
    {
        return $this->invExp . '/.write_barrier_' . $this->validateSlug($slug);
    }

    /**
     * Activa la barrera de escritura para impedir que sincronizadores desktop o importaciones modifiquen el inventario de la empresa.
     */
    public function activateWriteBarrier(string $slug): void
    {
        $barrierPath = $this->getWriteBarrierPath($slug);
        $meta = [
            'slug' => $slug,
            'activatedAt' => date('c'),
            'pid' => getmypid(),
            'reason' => 'company_deletion_in_progress',
        ];
        @file_put_contents($barrierPath, json_encode($meta, JSON_PRETTY_PRINT));
    }

    /**
     * Libera la barrera de escritura de la empresa.
     */
    public function releaseWriteBarrier(string $slug): void
    {
        $barrierPath = $this->getWriteBarrierPath($slug);
        if (file_exists($barrierPath)) {
            @unlink($barrierPath);
        }
    }

    /**
     * Comprueba estáticamente si una empresa tiene la barrera de escritura activa.
     */
    public static function isWriteBarrierActive(string $invExp, string $slug): bool
    {
        $clean = trim($slug);
        if ($clean === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $clean)) {
            return false;
        }
        $path = rtrim($invExp, '/\\') . '/.write_barrier_' . $clean;
        return file_exists($path);
    }

    /**
     * Determina si un archivo físico (o su variante peq_/original) está referenciado
     * por alguna publicación de otra empresa que permanecerá en la base de datos (slug != cleanSlug).
     *
     * Reglas estrictas:
     * 1. No compara solo basename (evita falsos positivos con nombres como '1.jpg' o '0.png').
     * 2. Comprueba rutas canónicas vinculadas al IKU (/iku/filename) o al slug (/slug/iku/filename).
     * 3. Si se referencia la imagen grande, se protege también su miniatura peq_, y viceversa.
     * 4. Si el IKU existe en otra empresa, todo el directorio IKU se protege como compartido.
     */
    public function isFileReferencedByOtherCompany(string $cleanSlug, string $subDir, string $filename): bool
    {
        $baseName = str_starts_with($filename, 'peq_') ? substr($filename, 4) : $filename;
        $thumbName = 'peq_' . $baseName;

        // 1. Si el archivo está dentro de una carpeta IKU (subDir != cleanSlug)
        if ($subDir !== $cleanSlug) {
            // A. Comprobar si otra empresa activa comparte el mismo IKU
            $otherWithIku = (int)$this->conn->fetchOne(
                "SELECT COUNT(*) FROM item_pub WHERE iku = ? AND slug != ?",
                [$subDir, $cleanSlug]
            );
            if ($otherWithIku > 0) {
                return true;
            }

            // B. Comprobar referencias canónicas en columnas thumb o img_big de otras empresas
            $canSub1 = "%/{$subDir}/{$baseName}%";
            $canSub2 = "%/{$subDir}/{$thumbName}%";
            $canSlug1 = "%/{$cleanSlug}/{$subDir}/{$baseName}%";
            $canSlug2 = "%/{$cleanSlug}/{$subDir}/{$thumbName}%";

            $refColumns = (int)$this->conn->fetchOne(
                "SELECT COUNT(*) FROM item_pub 
                 WHERE slug != ? 
                   AND (thumb LIKE ? OR thumb LIKE ? OR thumb LIKE ? OR thumb LIKE ?
                     OR img_big LIKE ? OR img_big LIKE ? OR img_big LIKE ? OR img_big LIKE ?)",
                [
                    $cleanSlug,
                    $canSub1, $canSub2, $canSlug1, $canSlug2,
                    $canSub1, $canSub2, $canSlug1, $canSlug2,
                ]
            );
            if ($refColumns > 0) {
                return true;
            }

            // C. Comprobar en extra (JSON de pictures y pathImg) de otras empresas
            // Soporta barras normales y barras escapadas por json_encode (\/)
            $extraRef = (int)$this->conn->fetchOne(
                "SELECT COUNT(*) FROM item_pub 
                 WHERE slug != ? 
                   AND (extra LIKE ? OR extra LIKE ?)
                   AND (extra LIKE ? OR extra LIKE ?)",
                [
                    $cleanSlug,
                    "%{$cleanSlug}%", "%{$subDir}%",
                    '%"' . $baseName . '"%', '%"' . $thumbName . '"%',
                ]
            );
            if ($extraRef > 0) {
                return true;
            }
        } else {
            // Archivo en la raíz de la empresa (sin subcarpeta IKU)
            $rootSlug1 = "%/{$cleanSlug}/{$baseName}%";
            $rootSlug2 = "%/{$cleanSlug}/{$thumbName}%";

            $refRoot = (int)$this->conn->fetchOne(
                "SELECT COUNT(*) FROM item_pub 
                 WHERE slug != ? 
                   AND (thumb LIKE ? OR thumb LIKE ? OR img_big LIKE ? OR img_big LIKE ? OR (extra LIKE ? AND (extra LIKE ? OR extra LIKE ?)))",
                [
                    $cleanSlug,
                    $rootSlug1, $rootSlug2,
                    $rootSlug1, $rootSlug2,
                    "%{$cleanSlug}%", '%"' . $baseName . '"%', '%"' . $thumbName . '"%'
                ]
            );
            if ($refRoot > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Genera la previsualización (dry-run) de la eliminación sin ejecutar mutaciones.
     */
    public function previewDeletion(string $slug): array
    {
        $cleanSlug = $this->validateSlug($slug);
        $startTime = microtime(true);

        // 1. Métricas SQL de la empresa
        $pubCount = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM item_pub WHERE slug = ?",
            [$cleanSlug]
        );

        $remateCount = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM remate WHERE owner_slug = ?",
            [$cleanSlug]
        );

        $usComCount = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM us_com WHERE iku IN (SELECT iku FROM item_pub WHERE slug = ?)",
            [$cleanSlug]
        );

        // 2. Escaneo de filesystem de fotografías locales
        $companyDir = $this->getCompanyImagesDir($cleanSlug);
        $authorizedRoot = realpath($companyDir);

        $scannedFiles = [];
        $exclusiveFiles = [];
        $sharedFiles = [];
        $symlinkRisks = [];
        $totalBytes = 0;
        $reclaimableBytes = 0;
        $candidateDirs = [];

        if ($authorizedRoot !== false && is_dir($authorizedRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($authorizedRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            // Obtener todos los IKUs de la empresa a borrar
            $companyIkus = $this->conn->fetchFirstColumn(
                "SELECT DISTINCT iku FROM item_pub WHERE slug = ?",
                [$cleanSlug]
            );
            $companyIkuMap = array_flip($companyIkus);

            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    $symlinkRisks[] = $item->getPathname();
                    continue;
                }

                if ($item->isDir()) {
                    $candidateDirs[] = $item->getPathname();
                    continue;
                }

                if ($item->isFile()) {
                    $realFilePath = realpath($item->getPathname());
                    if ($realFilePath === false || !str_starts_with($realFilePath, $authorizedRoot . DIRECTORY_SEPARATOR)) {
                        $symlinkRisks[] = $item->getPathname();
                        continue;
                    }

                    $fileSize = $item->getSize();
                    $totalBytes += $fileSize;
                    $filename = $item->getBasename();
                    $subDir = basename(dirname($realFilePath));

                    $scannedFiles[] = [
                        'relativePath' => str_replace($authorizedRoot, '', $realFilePath),
                        'filename' => $filename,
                        'subDir' => $subDir,
                        'sizeBytes' => $fileSize,
                    ];

                    // Comprobar referencias cruzadas canónicas: ¿está este archivo o IKU referenciado por otra empresa?
                    $isShared = $this->isFileReferencedByOtherCompany($cleanSlug, $subDir, $filename);

                    if ($isShared) {
                        $sharedFiles[] = [
                            'path' => $realFilePath,
                            'filename' => $filename,
                            'sizeBytes' => $fileSize,
                        ];
                    } else {
                        $exclusiveFiles[] = [
                            'path' => $realFilePath,
                            'relativePath' => ltrim(str_replace($authorizedRoot, '', $realFilePath), '/\\'),
                            'filename' => $filename,
                            'subDir' => $subDir,
                            'sizeBytes' => $fileSize,
                        ];
                        $reclaimableBytes += $fileSize;
                    }
                }
            }
        }

        // 3. Archivos declarados en BD pero faltantes en disco
        $dbPhotosDeclared = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM item_pub WHERE slug = ? AND (thumb != '' OR img_big != '' OR extra LIKE '%pictures%')",
            [$cleanSlug]
        );
        $missingFilesCount = max(0, $dbPhotosDeclared - count($scannedFiles));

        // 4. Evaluación de riesgos
        $risks = [];
        $canProceed = true;

        if (!empty($symlinkRisks)) {
            $risks[] = 'Se detectaron symlinks o rutas fuera de la raíz autorizada (' . count($symlinkRisks) . '). La eliminación automática se bloquea por seguridad.';
            $canProceed = false;
        }

        if ($remateCount > 0) {
            $risks[] = "La empresa posee {$remateCount} ofertas de remate activas que serán canceladas/eliminadas simultáneamente.";
        }

        if (count($sharedFiles) > 0) {
            $risks[] = 'Se detectaron ' . count($sharedFiles) . ' archivos físicos compartidos con otras empresas/publicaciones. Serán conservados en disco.';
        }

        return [
            'slug' => $cleanSlug,
            'canProceed' => $canProceed,
            'sqlImpact' => [
                'publicationsToDelete' => $pubCount,
                'rematesToDelete' => $remateCount,
                'usComToUnlink' => $usComCount,
                'preservedTables' => ['ng1_empresas', 'ng2_contactos', 'sys_com', 'sync_ml', 'mmentity'],
            ],
            'filesystemImpact' => [
                'imagesDirExists' => ($authorizedRoot !== false && is_dir($authorizedRoot)),
                'imagesPath' => $companyDir,
                'totalFilesScanned' => count($scannedFiles),
                'exclusiveFilesToDelete' => count($exclusiveFiles),
                'sharedFilesPreserved' => count($sharedFiles),
                'missingFilesCount' => $missingFilesCount,
                'candidateDirsCount' => count($candidateDirs),
                'totalBytes' => $totalBytes,
                'reclaimableBytes' => $reclaimableBytes,
                'formattedReclaimable' => $this->formatBytes($reclaimableBytes),
            ],
            'risks' => $risks,
            'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
        ];
    }

    /**
     * Ejecuta la eliminación administrativa con cuarentena y transacción SQL atómica.
     */
    public function executeDeletion(string $slug, string $confirmSlug): array
    {
        $cleanSlug = $this->validateSlug($slug);
        $cleanConfirm = trim($confirmSlug);

        if ($cleanSlug !== $cleanConfirm) {
            throw new \InvalidArgumentException("Confirmación tipográfica no coincide: se esperaba '{$cleanSlug}' pero se recibió '{$cleanConfirm}'.");
        }

        // 1. Previsualización y revalidación de aislamiento
        $preview = $this->previewDeletion($cleanSlug);
        if (!$preview['canProceed']) {
            throw new \RuntimeException('La eliminación fue cancelada por condiciones de riesgo de filesystem (symlinks o ambigüedad de rutas).');
        }

        $startTime = microtime(true);
        $companyDir = $this->getCompanyImagesDir($cleanSlug);
        $authorizedRoot = realpath($companyDir);

        // Lock para prevenir concurrencia y activar barrera de escritura contra sincronizadores/desktop
        $lockFile = $this->getQuarantineBaseDir() . '/.lock_' . $cleanSlug;
        $lockFp = @fopen($lockFile, 'w+');
        if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException("Existe una operación administrativa en progreso para la empresa '{$cleanSlug}'. Intente más tarde.");
        }

        $this->activateWriteBarrier($cleanSlug);

        $quarantineSessionDir = null;
        $movedFiles = [];

        try {
            // 2. Cuarentena de archivos físicos exclusivos
            if ($authorizedRoot !== false && is_dir($authorizedRoot) && $preview['filesystemImpact']['exclusiveFilesToDelete'] > 0) {
                $sessionToken = $cleanSlug . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                $quarantineSessionDir = $this->getQuarantineBaseDir() . '/' . $sessionToken;
                if (!is_dir($quarantineSessionDir)) {
                    @mkdir($quarantineSessionDir, 0755, true);
                }

                // Obtener lista fresca de exclusivos
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($authorizedRoot, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isFile() && !$item->isLink()) {
                        $realFilePath = realpath($item->getPathname());
                        if ($realFilePath === false || !str_starts_with($realFilePath, $authorizedRoot . DIRECTORY_SEPARATOR)) {
                            continue;
                        }

                        $filename = $item->getBasename();
                        $subDir = basename(dirname($realFilePath));

                        // Comprobar referencias cruzadas canónicas: variantes peq_/base e IKU/slug
                        $isShared = $this->isFileReferencedByOtherCompany($cleanSlug, $subDir, $filename);

                        if (!$isShared) {
                            $relPath = ltrim(str_replace($authorizedRoot, '', $realFilePath), '/\\');
                            $destPath = $quarantineSessionDir . '/' . $relPath;
                            $destDir = dirname($destPath);
                            if (!is_dir($destDir)) {
                                @mkdir($destDir, 0755, true);
                            }

                            $fileSize = $item->getSize();
                            if (@rename($realFilePath, $destPath)) {
                                $movedFiles[] = [
                                    'original' => $realFilePath,
                                    'quarantine' => $destPath,
                                    'size' => $fileSize,
                                ];
                            }
                        }
                    }
                }

                // Guardar manifiesto de cuarentena persistente pre-commit
                $manifest = [
                    'slug' => $cleanSlug,
                    'timestamp' => date('c'),
                    'status' => 'quarantined',
                    'databaseCommitted' => false,
                    'filesCount' => count($movedFiles),
                    'files' => $movedFiles,
                    'quarantinedFiles' => $movedFiles,
                ];
                @file_put_contents(
                    $quarantineSessionDir . '/manifest.json',
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }

            // 3. Transacción SQL en MySQL
            $this->conn->beginTransaction();
            try {
                // Desvincular en us_com preservando IKU si existe en otra empresa que permanece activa
                $this->conn->executeStatement(
                    "UPDATE us_com SET iku = NULL 
                     WHERE iku IN (SELECT iku FROM item_pub WHERE slug = ?)
                       AND iku NOT IN (SELECT iku FROM item_pub WHERE slug != ?)",
                    [$cleanSlug, $cleanSlug]
                );

                // Cancelar/Eliminar remates asociados
                $rematesDeleted = $this->conn->executeStatement(
                    "DELETE FROM remate WHERE owner_slug = ?",
                    [$cleanSlug]
                );

                // Eliminar inventario de item_pub
                $pubsDeleted = $this->conn->executeStatement(
                    "DELETE FROM item_pub WHERE slug = ?",
                    [$cleanSlug]
                );

                // Confirmar transacción de BD
                $this->conn->commit();

                // Actualizar manifiesto persistente tras commit exitoso
                if ($quarantineSessionDir !== null && file_exists($quarantineSessionDir . '/manifest.json')) {
                    $manifest['status'] = 'committed';
                    $manifest['databaseCommitted'] = true;
                    $manifest['committedAt'] = date('c');
                    @file_put_contents(
                        $quarantineSessionDir . '/manifest.json',
                        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }
            } catch (\Throwable $sqlException) {
                if ($this->conn->isTransactionActive()) {
                    $this->conn->rollBack();
                }

                // RESTITUCIÓN AUTOMÁTICA DESDE CUARENTENA ANTE FALLO PREVIO A COMMIT
                if ($quarantineSessionDir !== null && !empty($movedFiles)) {
                    foreach ($movedFiles as $mf) {
                        if (file_exists($mf['quarantine'])) {
                            $origDir = dirname($mf['original']);
                            if (!is_dir($origDir)) {
                                @mkdir($origDir, 0755, true);
                            }
                            @rename($mf['quarantine'], $mf['original']);
                        }
                    }
                    @unlink($quarantineSessionDir . '/manifest.json');
                    @rmdir($quarantineSessionDir);
                }

                throw new \RuntimeException(
                    'Fallo en la base de datos durante la eliminación. Los archivos físicos fueron restituidos inmediatamente de la cuarentena. Error: ' . $sqlException->getMessage()
                );
            }

            // 4. Limpieza de subdirectorios vacíos en images/{slug}
            $dirsRemovedCount = 0;
            if ($authorizedRoot !== false && is_dir($authorizedRoot)) {
                $subDirs = glob($authorizedRoot . '/*', GLOB_ONLYDIR);
                if (is_array($subDirs)) {
                    foreach ($subDirs as $sd) {
                        $items = scandir($sd);
                        if ($items !== false && count($items) === 2) { // solo . y ..
                            if (@rmdir($sd)) {
                                $dirsRemovedCount++;
                            }
                        }
                    }
                }

                // Si la carpeta de la empresa quedó completamente vacía, eliminarla también
                $rootItems = scandir($authorizedRoot);
                if ($rootItems !== false && count($rootItems) === 2) {
                    @rmdir($authorizedRoot);
                }
            }

            return [
                'slug' => $cleanSlug,
                'publicationsDeleted' => $pubsDeleted ?? 0,
                'rematesDeleted' => $rematesDeleted ?? 0,
                'filesQuarantined' => count($movedFiles),
                'sharedFilesPreserved' => $preview['filesystemImpact']['sharedFilesPreserved'],
                'dirsRemoved' => $dirsRemovedCount,
                'quarantineSession' => $quarantineSessionDir ? basename($quarantineSessionDir) : null,
                'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
            ];
        } finally {
            $this->releaseWriteBarrier($cleanSlug);
            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                @unlink($lockFile);
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
