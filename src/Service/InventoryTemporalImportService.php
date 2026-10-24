<?php

namespace App\Service;

use App\Entity\ItemPub;
use App\Service\Any\IkuGenerator\GeneratorIKU;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Servicio de selección, prevalidación en seco y resolución de conflictos para CSVs temporales de inventario.
 * 
 * RESTRICCIÓN DE SEGURIDAD ABSOLUTA:
 * Esta clase opera exclusivamente en MODO SOLO LECTURA respecto a MySQL.
 * Jamás ejecuta INSERT, UPDATE, DELETE, TRUNCATE ni ALTER sobre item_pub.
 * No realiza operaciones de movimiento ni borrado sobre el sistema de archivos de imágenes.
 */
class InventoryTemporalImportService
{
    private Connection $conn;
    private GeneratorIKU $generatorIku;
    private string $invExp;

    public function __construct(Connection $conn, GeneratorIKU $generatorIku, string $invExp)
    {
        $this->conn = $conn;
        $this->generatorIku = $generatorIku;
        $this->invExp = $invExp;
    }

    /**
     * Retorna la ruta física absoluta del directorio de exportación/importación administrativa.
     */
    public function getAdminExportDir(): string
    {
        return rtrim($this->invExp, '/\\') . '/admin_export/';
    }

    /**
     * Lista los archivos CSV temporales correspondientes al slug especificado en /public_html/inv/admin_export/.
     *
     * @param string $slug Identificador alfanumérico de la empresa.
     * @return array Lista de archivos ordenados de más reciente a más antiguo.
     * @throws \InvalidArgumentException Si el slug es inválido.
     */
    public function listTemporalFiles(string $slug): array
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '' || $cleanSlug === 'Todas las empresas' || !preg_match('/^[a-zA-Z0-9_-]+$/', $cleanSlug)) {
            throw new \InvalidArgumentException('El parámetro slug es obligatorio y debe ser alfanumérico.');
        }

        $dir = $this->getAdminExportDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = scandir($dir);
        if ($files === false) {
            return [];
        }

        $pattern = '/^tmp_inv_' . preg_quote($cleanSlug, '/') . '_(\d{8})_(\d{6})_([a-zA-Z0-9]+)\.csv$/';
        $result = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !is_file($dir . $file)) {
                continue;
            }

            if (!preg_match($pattern, $file, $matches)) {
                continue;
            }

            $fullPath = realpath($dir . $file);
            $realDir = realpath($dir);
            if ($fullPath === false || $realDir === false || !str_starts_with($fullPath, $realDir)) {
                continue; // Protección contra traversal
            }

            $size = filesize($fullPath);
            $mtime = filemtime($fullPath);

            // Validar legibilidad básica y contar líneas aproximadas (streaming O(1))
            $lineCount = 0;
            $hasValidHeader = false;
            $fp = @fopen($fullPath, 'r');
            if ($fp) {
                $header = fgetcsv($fp);
                if ($header !== false && count($header) >= 20) {
                    $hasValidHeader = true;
                    // Estimar o contar líneas hasta límite de seguridad de escaneo
                    while (!feof($fp)) {
                        $buffer = fread($fp, 8192);
                        $lineCount += substr_count($buffer, "\n");
                    }
                }
                fclose($fp);
            }

            $dateRaw = $matches[1] . $matches[2]; // YYYYMMDDHHmmss
            $formattedDate = \DateTimeImmutable::createFromFormat('YmdHis', $dateRaw);
            $isoDate = $formattedDate ? $formattedDate->format(\DateTimeInterface::ATOM) : date(\DateTimeInterface::ATOM, $mtime);

            $result[] = [
                'filename' => $file,
                'slug' => $cleanSlug,
                'size' => $size,
                'formattedSize' => $this->formatBytes($size),
                'createdAt' => $isoDate,
                'timestamp' => $mtime,
                'estimatedRows' => max(0, $lineCount),
                'isValidFormat' => $hasValidHeader,
            ];
        }

        // Ordenar desc por timestamp / createdAt
        usort($result, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $result;
    }

    /**
     * Valida y normaliza el slug de la empresa.
     *
     * @throws \InvalidArgumentException
     */
    public function validateSlug(string $slug): string
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '' || $cleanSlug === 'Todas las empresas' || !preg_match('/^[a-zA-Z0-9_-]+$/', $cleanSlug)) {
            throw new \InvalidArgumentException('El parámetro slug es obligatorio y debe ser alfanumérico.');
        }
        return $cleanSlug;
    }

    /**
     * Valida la existencia, ruta segura y formato de un archivo CSV temporal.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function getSecureCsvPath(string $slug, string $filename): string
    {
        $cleanSlug = $this->validateSlug($slug);

        $cleanFilename = basename($filename);
        $pattern = '/^tmp_inv_' . preg_quote($cleanSlug, '/') . '_\d{8}_\d{6}_[a-zA-Z0-9]+\.csv$/';
        if (!preg_match($pattern, $cleanFilename)) {
            throw new \InvalidArgumentException('El nombre del archivo no pertenece a la empresa seleccionada o tiene un formato no permitido.');
        }

        $dir = $this->getAdminExportDir();
        $fullPath = realpath($dir . $cleanFilename);
        $realDir = realpath($dir);

        if ($fullPath === false || $realDir === false || !str_starts_with($fullPath, $realDir) || !is_file($fullPath)) {
            throw new \RuntimeException('El archivo CSV temporal solicitado no existe o no es accesible.');
        }

        return $fullPath;
    }

    /**
     * Ejecuta la prevalidación en seco completa de un CSV temporal sin escribir en MySQL.
     * Compara los IKUs contra la base de datos global, detecta duplicados internos y externos,
     * evalúa conflictos de imágenes y aplica en memoria las resoluciones propuestas.
     *
     * @param string $slug Empresa seleccionada.
     * @param string $filename Nombre de archivo validado.
     * @param array $resolutions Mapa asociativo de decisiones [ rowHash => [ action, targetId?, newIku? ] ].
     * @return array Resumen métrico, lista de conflictos y candidatos clasificados.
     */
    public function prevalidateTemporalCsv(string $slug, string $filename, array $resolutions = []): array
    {
        $fullPath = $this->getSecureCsvPath($slug, $filename);

        $fp = fopen($fullPath, 'r');
        if (!$fp) {
            throw new \RuntimeException('No fue posible abrir el archivo CSV temporal para lectura.');
        }

        // Primera línea: cabecera
        $header = fgetcsv($fp);
        if ($header === false || count($header) < 20) {
            fclose($fp);
            throw new \RuntimeException('El archivo CSV no contiene una cabecera válida con los campos mínimos requeridos.');
        }

        // Limpiar BOM UTF-8 si está presente en la primera columna
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        $header = array_map('trim', $header);

        // Mapeo posicional flexible de columnas
        $colMap = array_flip($header);

        $rawRows = [];
        $csvIkuCounts = [];
        $collectedIkus = [];
        $lineNum = 1;

        while (($row = fgetcsv($fp)) !== false) {
            $lineNum++;
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue; // Omitir líneas completamente vacías
            }

            $item = $this->extractRowData($row, $colMap, $lineNum, $slug);
            $rawRows[] = $item;

            $cleanIku = $item['iku'];
            if ($cleanIku !== '') {
                $collectedIkus[$cleanIku] = true;
                if (!isset($csvIkuCounts[$cleanIku])) {
                    $csvIkuCounts[$cleanIku] = 0;
                }
                $csvIkuCounts[$cleanIku]++;
            }
        }
        fclose($fp);

        // 1. Consultar todos los IKUs recolectados en MySQL global en chunks de 500
        $existingByIku = [];
        $uniqueIkus = array_keys($collectedIkus);

        if (!empty($uniqueIkus)) {
            $paramType = class_exists(ArrayParameterType::class)
                ? ArrayParameterType::STRING
                : Connection::PARAM_STR_ARRAY;

            $chunks = array_chunk($uniqueIkus, 500);
            foreach ($chunks as $chunk) {
                $sql = "SELECT id, slug, id_src, iku, stt, is_active, updated_at, thumb, img_big, extras, pieza, mrk_id, mdl_id 
                        FROM item_pub 
                        WHERE iku IN (?)";
                $found = $this->conn->fetchAllAssociative(
                    $sql,
                    [$chunk],
                    [$paramType]
                );

                foreach ($found as $f) {
                    $k = $f['iku'];
                    if (!isset($existingByIku[$k])) {
                        $existingByIku[$k] = [];
                    }
                    $existingByIku[$k][] = $f;
                }
            }
        }

        // 2. Conteo de ocurrencias de cada IKU en publicaciones activas (descontando aquellas con resolución 'exclude')
        $activeCsvIkuCounts = [];
        foreach ($rawRows as $r) {
            $rowHash = $r['rowHash'];
            $res = $resolutions[$rowHash] ?? null;
            $action = is_array($res) ? ($res['action'] ?? null) : null;

            if ($action === 'exclude') {
                continue; // Filas excluidas no compiten en la importación activa
            }

            if ($action === 'generate_new_iku') {
                $proposedIku = trim((string)($res['newIku'] ?? ''));
                if ($proposedIku !== '' && ItemPub::isValidIku($proposedIku, $r['id_src'])) {
                    $activeCsvIkuCounts[$proposedIku] = ($activeCsvIkuCounts[$proposedIku] ?? 0) + 1;
                    continue;
                }
            }

            $cleanIku = $r['iku'];
            if ($cleanIku !== '') {
                $activeCsvIkuCounts[$cleanIku] = ($activeCsvIkuCounts[$cleanIku] ?? 0) + 1;
            }
        }

        // 3. Clasificar cada fila, detectar conflictos y evaluar resoluciones
        $classifiedRows = [];
        $conflictRows = [];

        $insertCount = 0;
        $updateCount = 0;
        $duplicateCount = 0;
        $conflictCount = 0;
        $resolvedCount = 0;
        $pendingCount = 0;
        $invalidCount = 0;
        $excludedCount = 0;
        $invalidatedResolutionsCount = 0;
        $hasImageConflicts = 0;

        foreach ($rawRows as $r) {
            $rowHash = $r['rowHash'];
            $iku = $r['iku'];
            $idSrc = $r['id_src'];
            $isSyntacticallyValid = $r['isValid'];
            $resolution = $resolutions[$rowHash] ?? null;
            $userAction = is_array($resolution) ? ($resolution['action'] ?? null) : null;
            $isRowExcluded = ($userAction === 'exclude');

            // Registros existentes en MySQL para este IKU
            $mysqlRecords = $existingByIku[$iku] ?? [];
            $mysqlCount = count($mysqlRecords);

            $existingMatches = array_map(function($m) {
                return [
                    'id' => (int)$m['id'],
                    'slug' => $m['slug'],
                    'id_src' => $m['id_src'],
                    'iku' => $m['iku'],
                    'pieza' => $m['pieza'] ?? '',
                    'stt' => (int)$m['stt'],
                    'isActive' => (bool)$m['is_active'],
                    'updatedAt' => $m['updated_at'],
                ];
            }, $mysqlRecords);

            $status = 'insert';
            $cause = null;
            $availableActions = [];

            if (!$isSyntacticallyValid) {
                $status = 'invalid';
                $cause = $r['validationError'];
                $availableActions = ['exclude'];
                $invalidCount++;
            } elseif ($isRowExcluded) {
                // Publicación excluida explícitamente por el usuario
                $status = 'internal_conflict';
                $cause = 'Publicación excluida de importación por decisión del operador.';
                $availableActions = ['exclude'];
                $conflictCount++;
            } else {
                // Verificar si el IKU aparece más de una vez entre las publicaciones activas del CSV
                $activeCount = $activeCsvIkuCounts[$iku] ?? 0;
                $isDuplicateInCsv = $activeCount > 1;

                if ($mysqlCount === 0 && !$isDuplicateInCsv) {
                    // No existe en BD y no se repite entre activas en CSV -> INSERT limpio
                    $status = 'insert';
                    $insertCount++;
                } elseif ($isDuplicateInCsv) {
                    // Conflicto interno: el mismo IKU se repite entre publicaciones activas a importar
                    $status = 'internal_conflict';
                    $cause = 'IKU repetido dentro del archivo CSV (' . $activeCount . ' publicaciones activas)' . ($mysqlCount > 0 ? ' y además existe en MySQL.' : '.');
                    $availableActions = ['generate_new_iku', 'exclude'];
                    $conflictCount++;
                } else {
                    // Existe en MySQL global
                    $differentSlugRecords = array_filter($mysqlRecords, fn($m) => $m['slug'] !== $slug);

                    if (!empty($differentSlugRecords)) {
                        // Conflicto entre empresas (Cross-company)
                        $otherSlugs = array_unique(array_map(fn($m) => $m['slug'], $differentSlugRecords));
                        $status = 'cross_company_conflict';
                        $cause = 'IKU colisiona con otra empresa en MySQL: ' . implode(', ', $otherSlugs) . '.';
                        $availableActions = ['generate_new_iku', 'exclude'];
                        $conflictCount++;
                    } else {
                        // Todos pertenecen al mismo slug
                        if ($mysqlCount > 1) {
                            // Múltiples registros en MySQL para el mismo slug: NUNCA clasificar como update inequívoco
                            $differentIdSrc = array_filter($mysqlRecords, fn($m) => $m['id_src'] !== $idSrc);
                            if (empty($differentIdSrc)) {
                                // Mismo slug, mismo id_src: duplicados históricos en BD
                                $status = 'duplicate_same_idsrc';
                                $cause = 'Existen ' . $mysqlCount . ' publicaciones con este mismo IKU e idSrc en la empresa en BD.';
                                $availableActions = ['keep_existing', 'generate_new_iku', 'exclude'];
                                $duplicateCount++;
                                $conflictCount++;
                            } else {
                                // Mismo slug pero diferente id_src: colisión de IKU
                                $status = 'conflict_diff_idsrc';
                                $cause = 'IKU colisiona con otra publicación distinta de la misma empresa (idSrc no coincide).';
                                $availableActions = ['generate_new_iku', 'exclude'];
                                $conflictCount++;
                            }
                        } else {
                            // Exactamente 1 registro en BD del mismo slug
                            $singleMatch = $mysqlRecords[0];
                            if ($singleMatch['id_src'] === $idSrc) {
                                $status = 'update';
                                $updateCount++;
                            } else {
                                $status = 'conflict_diff_idsrc';
                                $cause = 'IKU coincide con ID central ' . $singleMatch['id'] . ' pero difiere en idSrc (' . $singleMatch['id_src'] . ' vs ' . $idSrc . ').';
                                $availableActions = ['generate_new_iku', 'exclude'];
                                $conflictCount++;
                            }
                        }
                    }
                }
            }

            // 3. Evaluar resoluciones enviadas por el usuario (incluye exclusión y validación estricta al revalidar)
            $resolution = $resolutions[$rowHash] ?? null;
            $isResolved = false;
            $hasImageConflict = false;
            $imageConflictMessage = null;
            $appliedResolution = null;

            if ($status !== 'insert' && $status !== 'update' && $resolution !== null && is_array($resolution)) {
                $action = $resolution['action'] ?? '';
                if ($action === 'exclude') {
                    $isResolved = true;
                    $appliedResolution = [
                        'action' => 'exclude',
                    ];
                    $resolvedCount++;
                    $excludedCount++;
                } elseif ($action === 'generate_new_iku' && $status !== 'invalid') {
                    $newIku = trim((string)($resolution['newIku'] ?? ''));
                    if (ItemPub::isValidIku($newIku, $idSrc)) {
                        // Comprobar si el nuevo IKU propuesto existe actualmente en MySQL (revalidación)
                        $isAlreadyInDb = false;
                        if (!empty($existingByIku[$newIku])) {
                            $isAlreadyInDb = true;
                        } else {
                            $dbCheck = (int)$this->conn->fetchOne("SELECT COUNT(*) FROM item_pub WHERE iku = ?", [$newIku]);
                            if ($dbCheck > 0) {
                                $isAlreadyInDb = true;
                            }
                        }

                        if ($isAlreadyInDb) {
                            $invalidatedResolutionsCount++;
                            $hasImageConflict = false;
                            $imageConflictMessage = 'La propuesta de IKU (' . $newIku . ') ya no es válida porque fue registrada en MySQL.';
                        } else {
                            // Verificar si el cambio de IKU produce conflicto con carpetas de imágenes
                            $imageAmbiguity = $this->detectImageConflict($r, $iku, $newIku);
                            if ($imageAmbiguity !== null) {
                                $hasImageConflict = true;
                                $imageConflictMessage = $imageAmbiguity;
                                $hasImageConflicts++;
                                $invalidatedResolutionsCount++;
                            } else {
                                $isResolved = true;
                                $appliedResolution = [
                                    'action' => 'generate_new_iku',
                                    'newIku' => $newIku,
                                ];
                                $resolvedCount++;
                            }
                        }
                    } else {
                        $invalidatedResolutionsCount++;
                    }
                } elseif ($action === 'keep_existing') {
                    $targetId = (int)($resolution['targetId'] ?? 0);
                    $existsInMatches = array_filter($mysqlRecords, fn($m) => (int)$m['id'] === $targetId);
                    if (!empty($existsInMatches)) {
                        $isResolved = true;
                        $appliedResolution = [
                            'action' => 'keep_existing',
                            'targetId' => $targetId,
                        ];
                        $resolvedCount++;
                    } else {
                        $invalidatedResolutionsCount++;
                    }
                }
            }

            if ($status === 'invalid') {
                if (!$isResolved) {
                    $pendingCount++;
                }
            } elseif ($status !== 'insert' && $status !== 'update') {
                if (!$isResolved) {
                    $pendingCount++;
                }
            }

            $r['status'] = $status;
            $r['cause'] = $cause;
            $r['availableActions'] = $availableActions;
            $r['existingMatches'] = $existingMatches;
            $r['isResolved'] = $isResolved;
            $r['hasImageConflict'] = $hasImageConflict;
            $r['imageConflictMessage'] = $imageConflictMessage;
            $r['appliedResolution'] = $appliedResolution;

            $classifiedRows[] = $r;

            if ($status !== 'insert' && $status !== 'update') {
                $conflictRows[] = $r;
            }
        }

        $totalRows = count($rawRows);
        $classifiedTotal = $insertCount + $updateCount + $conflictCount + $invalidCount;
        if ($classifiedTotal !== $totalRows) {
            throw new \LogicException(sprintf(
                'Inconsistencia en la clasificación de filas: procesadas (%d) != clasificadas (%d).',
                $totalRows,
                $classifiedTotal
            ));
        }

        return [
            'filename' => $filename,
            'slug' => $slug,
            'summary' => [
                'totalRows' => $totalRows,
                'insertCount' => $insertCount,
                'updateCount' => $updateCount,
                'duplicateCount' => $duplicateCount,
                'conflictCount' => $conflictCount,
                'resolvedCount' => $resolvedCount,
                'pendingCount' => $pendingCount,
                'invalidCount' => $invalidCount,
                'excludedCount' => $excludedCount,
                'invalidatedResolutionsCount' => $invalidatedResolutionsCount,
                'hasImageConflicts' => $hasImageConflicts,
                'isReadyForImport' => ($totalRows > 0 && $pendingCount === 0 && ($totalRows - $excludedCount) > 0 && $hasImageConflicts === 0),
            ],
            'conflicts' => $conflictRows,
            'rows' => $classifiedRows,
        ];
    }

    /**
     * Genera un IKU único garantizado a nivel global utilizando GeneratorIku.
     * Comprueba unicidad contra MySQL global, contra el CSV y contra propuestas previas.
     *
     * @param array $excludeIkus Lista de IKUs a excluir en memoria.
     * @return string IKU canónico Base62 de 12 caracteres.
     */
    public function generateGuaranteedUniqueIku(array $excludeIkus = []): string
    {
        $excludeMap = array_flip($excludeIkus);
        $attempts = 0;
        $maxAttempts = 50;

        do {
            $attempts++;
            $candidate = $this->generatorIku->generate(12);

            if (!ItemPub::isValidIku($candidate)) {
                continue;
            }

            if (isset($excludeMap[$candidate])) {
                continue;
            }

            // Consultar en MySQL global
            $count = (int)$this->conn->fetchOne(
                "SELECT COUNT(*) FROM item_pub WHERE iku = ?",
                [$candidate]
            );

            if ($count === 0) {
                return $candidate;
            }
        } while ($attempts < $maxAttempts);

        throw new \RuntimeException('No fue posible generar un IKU único tras ' . $maxAttempts . ' intentos.');
    }

    /**
     * Resuelve en masa todas las colisiones que admitan 'generate_new_iku',
     * devolviendo un mapa de resoluciones listo para previsualizar.
     */
    public function resolveAllConflicts(string $slug, string $filename, array $currentResolutions = []): array
    {
        $preval = $this->prevalidateTemporalCsv($slug, $filename, $currentResolutions);
        $conflicts = $preval['conflicts'];

        $usedIkus = [];
        // Recoger todos los IKUs del CSV original
        foreach ($preval['rows'] as $row) {
            if (!empty($row['iku'])) {
                $usedIkus[] = $row['iku'];
            }
        }
        // Recoger IKUs de resoluciones existentes
        foreach ($currentResolutions as $res) {
            if (!empty($res['newIku'])) {
                $usedIkus[] = $res['newIku'];
            }
        }

        $resolutions = $currentResolutions;

        foreach ($conflicts as $c) {
            $rowHash = $c['rowHash'];
            // Si ya está resuelto, conservar
            if ($c['isResolved']) {
                continue;
            }

            // Si admite generación de nuevo IKU y no tiene conflicto de imagen insalvable
            if (in_array('generate_new_iku', $c['availableActions'], true)) {
                $newIku = $this->generateGuaranteedUniqueIku($usedIkus);
                $usedIkus[] = $newIku;

                $resolutions[$rowHash] = [
                    'action' => 'generate_new_iku',
                    'newIku' => $newIku,
                ];
            }
        }

        return $resolutions;
    }

    /**
     * Evalúa si cambiar un IKU genera referencias ambiguas a carpetas o rutas fotográficas.
     * No realiza copias ni movimientos en disco; únicamente bloquea la fila para prevenir enlaces rotos.
     */
    private function detectImageConflict(array $row, string $originalIku, string $newIku): ?string
    {
        if ($originalIku === '' || $newIku === '' || $originalIku === $newIku) {
            return null;
        }

        $fieldsToCheck = [
            'thumb' => $row['thumb'] ?? '',
            'img_big' => $row['img_big'] ?? '',
        ];

        // Revisar extras
        $extras = $row['extras'];
        if (is_array($extras)) {
            if (!empty($extras['pathImg']) && is_string($extras['pathImg'])) {
                $fieldsToCheck['extra.pathImg'] = $extras['pathImg'];
            }
            if (!empty($extras['pictures']) && is_array($extras['pictures'])) {
                foreach ($extras['pictures'] as $idx => $pic) {
                    if (is_string($pic)) {
                        $fieldsToCheck['extra.pictures[' . $idx . ']'] = $pic;
                    }
                }
            }
        }

        foreach ($fieldsToCheck as $fieldName => $path) {
            if (str_contains($path, $originalIku)) {
                return sprintf(
                    'El campo "%s" contiene la referencia al IKU original ("%s"). Reemplazarlo dejaría imágenes huérfanas sin renombrar los archivos en disco.',
                    $fieldName,
                    $originalIku
                );
            }
        }

        return null;
    }

    /**
     * Extrae y normaliza los datos de una fila del CSV.
     */
    private function extractRowData(array $row, array $colMap, int $lineNum, string $slug): array
    {
        $get = function(string $col, $default = '') use ($row, $colMap) {
            $idx = $colMap[$col] ?? null;
            return ($idx !== null && isset($row[$idx])) ? trim((string)$row[$idx]) : $default;
        };

        $id = (int)$get('id', 0);
        $type = (int)$get('type', 0);
        $stt = (int)$get('stt', 0);
        $idSrc = $get('id_src', '');
        $iku = $get('iku', '');
        $src = $get('src', '');
        $fuente = $get('fuente', '');
        $thumb = $get('thumb', '');
        $imgBig = $get('img_big', '');
        $price = (float)$get('price', 0);
        $costo = (float)$get('costo', 0);
        $link = $get('link', '');
        $isActive = in_array(strtolower($get('is_active', '1')), ['1', 'true', 'yes'], true);
        $pieza = $get('pieza', '');
        $mrkId = (int)$get('mrk_id', 0);
        $mdlId = (int)$get('mdl_id', 0);
        $anioInicio = (int)$get('anio_inicio', 0);
        $anioFinRaw = $get('anio_fin', '');
        $anioFin = ($anioFinRaw !== '' && is_numeric($anioFinRaw)) ? (int)$anioFinRaw : null;
        $lado = $get('lado', '');
        $poss = $get('poss', '');
        $detalles = $get('detalles', '');
        $variantes = $get('variantes', '');
        $waId = $get('wa_id', '');
        $taId = (int)$get('ta_id', 0);
        $fromDev = $get('from_dev', 'admin_csv');
        $created = $get('created', date('Y-m-d H:i:s'));
        $updatedAt = $get('updated_at', date('Y-m-d H:i:s'));

        $extrasRaw = $get('extras', '');
        $extras = null;
        if ($extrasRaw !== '') {
            $decoded = json_decode($extrasRaw, true);
            $extras = is_array($decoded) ? $decoded : null;
        }

        // Generar hash inequívoco e inmutable de la fila
        $rowHash = sha1($slug . '|' . $lineNum . '|' . $iku . '|' . $idSrc . '|' . $src . '|' . $created);

        // Validaciones sintácticas
        $isValid = true;
        $validationError = null;

        if ($iku === '' || !ItemPub::isValidIku($iku, $idSrc)) {
            $isValid = false;
            $validationError = 'IKU ausente o con formato no canónico (debe tener 12 caracteres Base62 alfanuméricos).';
        } elseif ($idSrc === '') {
            $isValid = false;
            $validationError = 'El campo id_src es obligatorio para identificar la pieza en origen.';
        } elseif ($type < 0) {
            $isValid = false;
            $validationError = 'El tipo de publicación (type) no puede ser un entero negativo.';
        }

        return [
            'rowHash' => $rowHash,
            'lineNum' => $lineNum,
            'id' => $id,
            'slug' => $slug,
            'iku' => $iku,
            'id_src' => $idSrc,
            'src' => $src,
            'type' => $type,
            'stt' => $stt,
            'fuente' => $fuente,
            'thumb' => $thumb,
            'img_big' => $imgBig,
            'price' => $price,
            'costo' => $costo,
            'link' => $link,
            'is_active' => $isActive,
            'pieza' => $pieza,
            'mrk_id' => $mrkId,
            'mdl_id' => $mdlId,
            'anio_inicio' => $anioInicio,
            'anio_fin' => $anioFin,
            'lado' => $lado,
            'poss' => $poss,
            'detalles' => $detalles,
            'variantes' => $variantes !== '' ? $variantes : null,
            'wa_id' => $waId,
            'ta_id' => $taId,
            'from_dev' => $fromDev,
            'created' => $created,
            'updated_at' => $updatedAt,
            'extras' => $extras,
            'isValid' => $isValid,
            'validationError' => $validationError,
        ];
    }

    /**
     * Ejecuta la importación definitiva de un archivo CSV temporal en item_pub (Fase 2).
     *
     * 1. Revalida en caliente mediante prevalidateTemporalCsv(). Aborta inmediatamente si isReadyForImport !== true.
     * 2. Transacción atómica completa con rollback íntegro ante cualquier error.
     * 3. INSERT: omite ID antiguo del CSV, aprovecha AUTO_INCREMENT y conserva IKU definitivo.
     * 4. UPDATE: exclusivo para el mismo slug con targetId exacto sin ambigüedad.
     * 5. Hidratación de extra.idSr con el ID definitivo asignado por MySQL, conservando intacto el resto de extra.
     * 6. Garantiza unicidad global de IKU durante la corrida con bloqueo de concurrencia FOR UPDATE.
     * 7. Bloquea cualquier cambio de IKU que genere conflicto de imágenes con el sistema de archivos.
     * 8. Tras confirmación (commit), elimina exclusivamente el archivo CSV temporal (unlink). Si falla, registra estado recuperable.
     *
     * @param string $slug Slug alfanumérico estricto de la empresa.
     * @param string $filename Nombre del archivo temporal CSV.
     * @param array $resolutions Mapa asociativo de resoluciones [ rowHash => [ action, targetId?, newIku? ] ].
     * @return array Resumen de importación ejecutada.
     */
    public function executeImportTemporal(string $slug, string $filename, array $resolutions = []): array
    {
        $cleanSlug = $this->validateSlug($slug);
        $barrierFile = rtrim($this->invExp, '/\\') . '/.write_barrier_' . $cleanSlug;
        if (file_exists($barrierFile)) {
            throw new \RuntimeException("La empresa '{$cleanSlug}' se encuentra en un proceso de depuración o eliminación administrativa activa. Importaciones bloqueadas temporalmente.");
        }

        $startTime = microtime(true);
        $fullPath = $this->getSecureCsvPath($cleanSlug, $filename);

        // 1. Revalidación en caliente contra el CSV y el estado actual de MySQL
        $preval = $this->prevalidateTemporalCsv($slug, $filename, $resolutions);
        $summary = $preval['summary'] ?? [];

        if (!($summary['isReadyForImport'] ?? false)) {
            throw new \RuntimeException('La importación fue rechazada porque la prevalidación indica que el archivo no está listo para importar (existen conflictos pendientes o colisiones no resueltas).');
        }
        if (($summary['pendingCount'] ?? 0) > 0) {
            throw new \RuntimeException('La importación no puede continuar porque existen ' . $summary['pendingCount'] . ' publicaciones pendientes de resolución.');
        }
        if (($summary['hasImageConflicts'] ?? 0) > 0) {
            throw new \RuntimeException('La importación no puede continuar porque existen colisiones de imágenes con el sistema de archivos.');
        }
        if (($summary['invalidatedResolutionsCount'] ?? 0) > 0) {
            throw new \RuntimeException('Una o más resoluciones propuestas quedaron invalidadas debido a cambios concurrentes en la base de datos.');
        }

        // 2. Abrir CSV temporal para lectura de datos
        $fp = fopen($fullPath, 'r');
        if (!$fp) {
            throw new \RuntimeException('No fue posible abrir el archivo CSV temporal para ejecución.');
        }

        $header = fgetcsv($fp);
        if ($header === false || count($header) < 20) {
            fclose($fp);
            throw new \RuntimeException('El archivo CSV temporal no contiene una cabecera válida con los campos mínimos.');
        }

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        $header = array_map('trim', $header);
        $colMap = array_flip($header);

        $insertedCount = 0;
        $updatedCount = 0;
        $excludedCount = 0;
        $seenIkusInRun = [];

        // 3. Ejecución atómica bajo transacción completa
        $this->conn->beginTransaction();
        try {
            $lineNum = 1;
            while (($row = fgetcsv($fp)) !== false) {
                $lineNum++;
                if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                    continue; // Omitir filas vacías
                }

                $item = $this->extractRowData($row, $colMap, $lineNum, $slug);
                $rowHash = $item['rowHash'];
                $res = $resolutions[$rowHash] ?? null;
                $action = is_array($res) ? ($res['action'] ?? null) : null;

                // A. Exclusiones de publicaciones
                if ($action === 'exclude') {
                    $excludedCount++;
                    continue;
                }

                // B. Determinar IKU definitivo y validar imágenes
                $originalIku = $item['iku'];
                $finalIku = $originalIku;
                if ($action === 'generate_new_iku') {
                    $proposedIku = trim((string)($res['newIku'] ?? ''));
                    if (!ItemPub::isValidIku($proposedIku, $item['id_src'])) {
                        throw new \RuntimeException("Propuesta de IKU inválida ('{$proposedIku}') en fila {$lineNum}.");
                    }
                    $imgConflict = $this->detectImageConflict($item, $originalIku, $proposedIku);
                    if ($imgConflict !== null) {
                        throw new \RuntimeException("Conflicto de imágenes al alterar IKU en fila {$lineNum}: {$imgConflict}");
                    }
                    $finalIku = $proposedIku;
                }

                // C. Garantizar unicidad global en la corrida
                if (isset($seenIkusInRun[$finalIku])) {
                    throw new \RuntimeException("Violación de unicidad de IKU: el IKU '{$finalIku}' se procesó más de una vez en esta importación (fila {$lineNum}).");
                }
                $seenIkusInRun[$finalIku] = true;

                // D. Consultar MySQL con bloqueo FOR UPDATE para evaluar colisión o existencia
                $dbMatches = $this->conn->fetchAllAssociative(
                    "SELECT id, slug, id_src, iku, stt, is_active, price, costo, thumb, img_big, pieza, mrk_id, mdl_id, anio_inicio, anio_fin, lado, poss, detalles, variantes, extras FROM item_pub WHERE iku = ? FOR UPDATE",
                    [$finalIku]
                );
                $dbCount = count($dbMatches);

                $now = date('Y-m-d H:i:s');
                $extras = is_array($item['extras']) ? $item['extras'] : [];

                if ($dbCount === 0) {
                    // --- INSERT: nuevo AUTO_INCREMENT ignorando ID antiguo ---
                    // Regla: En importación automática, un registro nuevo nunca entra directamente como stt = 3
                    $insertStt = ((int) $item['stt'] === 3) ? 302 : (int) $item['stt'];

                    $insertData = [
                        'slug' => $slug,
                        'type' => $item['type'],
                        'stt' => $insertStt,
                        'id_src' => $item['id_src'],
                        'iku' => $finalIku,
                        'src' => $item['src'],
                        'fuente' => $item['fuente'],
                        'thumb' => $item['thumb'],
                        'img_big' => $item['img_big'],
                        'price' => $item['price'],
                        'costo' => $item['costo'],
                        'link' => $item['link'],
                        'is_active' => $item['is_active'] ? 1 : 0,
                        'pieza' => $item['pieza'],
                        'mrk_id' => $item['mrk_id'],
                        'mdl_id' => $item['mdl_id'],
                        'anio_inicio' => $item['anio_inicio'],
                        'anio_fin' => $item['anio_fin'],
                        'lado' => $item['lado'],
                        'poss' => $item['poss'],
                        'detalles' => $item['detalles'],
                        'variantes' => $item['variantes'],
                        'wa_id' => $item['wa_id'],
                        'ta_id' => $item['ta_id'],
                        'from_dev' => $item['from_dev'] ?: 'admin_csv',
                        'created' => $item['created'] ?: $now,
                        'updated_at' => $now,
                        'extras' => null, // Se hidrata inmediatamente con idSr
                    ];

                    $this->conn->insert('item_pub', $insertData);
                    $finalId = (int)$this->conn->lastInsertId();

                    // Hidratar extra.idSr conservando intacto el resto de extras
                    $extras['idSr'] = $finalId;
                    $this->conn->update('item_pub', [
                        'extras' => json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ], ['id' => $finalId]);

                    $insertedCount++;
                } else {
                    // --- UPDATE: únicamente si pertenece al mismo slug sin ambigüedad ---
                    $targetId = null;
                    $targetRecord = null;
                    if ($action === 'keep_existing') {
                        $requestedTargetId = (int)($res['targetId'] ?? 0);
                        $matchedTarget = array_filter($dbMatches, fn($m) => (int)$m['id'] === $requestedTargetId);
                        if (empty($matchedTarget)) {
                            throw new \RuntimeException("El targetId {$requestedTargetId} especificado para resolver {$finalIku} no existe en MySQL.");
                        }
                        $targetRecord = reset($matchedTarget);
                        if ($targetRecord['slug'] !== $slug) {
                            throw new \RuntimeException("El registro ID {$requestedTargetId} pertenece a otra empresa ('{$targetRecord['slug']}'). Modificación rechazada.");
                        }
                        $targetId = $requestedTargetId;
                    } elseif ($dbCount === 1) {
                        $single = $dbMatches[0];
                        if ($single['slug'] !== $slug) {
                            throw new \RuntimeException("El IKU '{$finalIku}' pertenece a otra empresa ('{$single['slug']}'). Modificación rechazada.");
                        }
                        if ($single['id_src'] !== $item['id_src']) {
                            throw new \RuntimeException("Conflicto no resuelto de idSrc en IKU '{$finalIku}' (BD: '{$single['id_src']}' vs CSV: '{$item['id_src']}').");
                        }
                        $targetRecord = $single;
                        $targetId = (int)$single['id'];
                    } else {
                        throw new \RuntimeException("Existen múltiples registros en BD para el IKU '{$finalIku}' sin resolución unívoca keep_existing.");
                    }

                    $finalId = $targetId;
                    $extras['idSr'] = $finalId;

                    $rawUpdateData = [
                        'type' => $item['type'],
                        'stt' => $item['stt'],
                        'id_src' => $item['id_src'],
                        'iku' => $finalIku,
                        'src' => $item['src'],
                        'fuente' => $item['fuente'],
                        'thumb' => $item['thumb'],
                        'img_big' => $item['img_big'],
                        'price' => $item['price'],
                        'costo' => $item['costo'],
                        'link' => $item['link'],
                        'is_active' => $item['is_active'] ? 1 : 0,
                        'pieza' => $item['pieza'],
                        'mrk_id' => $item['mrk_id'],
                        'mdl_id' => $item['mdl_id'],
                        'anio_inicio' => $item['anio_inicio'],
                        'anio_fin' => $item['anio_fin'],
                        'lado' => $item['lado'],
                        'poss' => $item['poss'],
                        'detalles' => $item['detalles'],
                        'variantes' => $item['variantes'],
                        'wa_id' => $item['wa_id'],
                        'ta_id' => $item['ta_id'],
                        'from_dev' => $item['from_dev'] ?: 'admin_csv',
                        'updated_at' => $now,
                        'extras' => json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ];

                    // Aplicar Super Blindaje de stt = 3 sobre la fila existente
                    $updateData = InventoryShieldHelper::applyShield($targetRecord, $rawUpdateData);

                    $this->conn->update('item_pub', $updateData, ['id' => $finalId, 'slug' => $slug]);
                    $updatedCount++;
                }
            }

            fclose($fp);
            unset($fp);

            // Confirmar transacción de base de datos
            $this->conn->commit();
        } catch (\Throwable $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        // 4. Eliminación atómica del archivo CSV importado con reporte recuperable
        $csvDeleted = false;
        $csvDeleteWarning = null;
        try {
            if (file_exists($fullPath)) {
                $csvDeleted = @unlink($fullPath);
                if (!$csvDeleted) {
                    $csvDeleteWarning = 'Los registros fueron importados e hidratados con éxito en MySQL, pero no fue posible eliminar el archivo temporal del disco (' . basename($fullPath) . '). Debe eliminarse manualmente para evitar reimportaciones accidentales.';
                }
            }
        } catch (\Throwable $te) {
            $csvDeleteWarning = 'Error al intentar eliminar el archivo temporal tras la importación: ' . $te->getMessage();
        }

        return [
            'filename' => $filename,
            'slug' => $slug,
            'totalRows' => $insertedCount + $updatedCount + $excludedCount,
            'insertedCount' => $insertedCount,
            'updatedCount' => $updatedCount,
            'excludedCount' => $excludedCount,
            'csvDeleted' => $csvDeleted,
            'csvDeleteWarning' => $csvDeleteWarning,
            'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
