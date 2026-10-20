<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Servicio experto en importación masiva de inventario.
 * Optimizado para bajos recursos y alto volumen (DBAL Nativo).
 */
class InventoryImportService
{
	private Connection $conn;
	private int $batchSize = 500;
	private string $invExp;

	public function __construct(Connection $conn, string $invExp)
	{
		$this->conn = $conn;
		$this->invExp = $invExp;
	}

	/**
	 * Exporta registros desde la BD a un CSV usando SQL nativo para máxima eficiencia.
	 */
	public function exportFromDbToCsv(string $slug): array
	{
		$sql = "SELECT id, type, stt, id_src, iku, src, fuente, thumb, img_big, price, costo, link, is_active, pieza, mrk_id, mdl_id, anio_inicio, anio_fin, lado, poss, detalles, extras, wa_id, ta_id, created 
				FROM item_pub 
				WHERE slug = :slug AND stt <= 499";
		
		$result = $this->conn->iterateAssociative($sql, ['slug' => $slug]);

		$filename = $slug . '_export.csv';
		$exportDir = $this->invExp . 'export/';
		$path = $exportDir . $filename;
		
		if (!is_dir($exportDir)) {
			mkdir($exportDir, 0777, true);
		}

		$handle = fopen($path, 'w');
		if (!$handle) {
			throw new \Exception("No se pudo crear el archivo de exportación en: " . $path);
		}

		// Encabezado (opcional, importFromCsv lo salta)
		fputcsv($handle, [
			'id', 'type', 'stt', 'id_src', 'iku', 'src', 'fuente', 'thumb', 'img_big', 
			'price', 'costo', 'link', 'is_active', 'pieza', 'mrk_id', 'mdl_id', 
			'anio_inicio', 'anio_fin', 'lado', 'poss', 'detalles', 'extras', 'wa_id', 'ta_id', 'created'
		]);

		$count = 0;
		foreach ($result as $row) {
			// Formatear booleanos para el CSV
			$row['is_active'] = $row['is_active'] ? '1' : '0';
			
			// Asegurar que extras sea una cadena
			if (isset($row['extras']) && (is_array($row['extras']) || is_object($row['extras']))) {
				$row['extras'] = json_encode($row['extras']);
			}
			
			fputcsv($handle, array_values($row));
			$count++;
		}

		fclose($handle);

		return [
			'total'    => $count,
			'filename' => $filename,
			'path'     => '/inv/export/' . $filename
		];
	}

	/**
	 * Importa registros desde un CSV usando SQL nativo para máxima eficiencia.
	 * 
	 * Reglas:
	 * 1. Si no existe idSrc -> INSERT.
	 * 2. Si existe idSrc y stt < 501 -> UPDATE (Sobreescribir).
	 * 3. Si existe idSrc y stt >= 501 -> IGNORAR (Borrado lógico).
	 */
	public function importFromCsv(UploadedFile $file, string $slug): array
	{
		$handle = fopen($file->getRealPath(), 'r');
		if (!$handle) {
			throw new \Exception("No se pudo abrir el archivo CSV.");
		}

		// Saltar encabezado
		fgetcsv($handle);

		$processed = 0;
		$updated = 0;
		$inserted = 0;
		$ignored = 0;

		$batch = [];
		$now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

		while (($row = fgetcsv($handle)) !== false) {

			if (count($row) < 20) continue; // Validación mínima de columnas

			$batch[] = [
				'type'        => (int) $row[1],
				'stt'         => (int) $row[2],
				'id_src'      => $row[3],
				'iku'         => $row[4],
				'src'         => $row[5],
				'fuente'      => $row[6],
				'thumb'       => $row[7],
				'img_big'     => $row[8],
				'price'       => (float) $row[9],
				'costo'       => (float) $row[10],
				'link'        => $row[11],
				'is_active'   => (int) ($row[12] === 'true' || $row[12] === '1'),
				'pieza'       => $row[13],
				'mrk_id'      => (int) $row[14],
				'mdl_id'      => (int) $row[15],
				'anio_inicio' => (int) $row[16],
				'anio_fin'    => (int) $row[17],
				'lado'        => $row[18],
				'poss'        => $row[19],
				'detalles'    => $row[20],
				'extras'      => $row[21], // JSON string serializado
				'wa_id'       => $row[22],
				'ta_id'       => (int) $row[23],
				'slug'        => $slug,
				'from_dev'    => 'desktop',
				'created'     => $row[24] ?? $now,
				'updated_at'  => $now
			];

			if (count($batch) >= $this->batchSize) {
				$results = $this->processBatch($batch, $slug);
				$inserted += $results['ins'];
				$updated  += $results['upd'];
				$ignored  += $results['ign'];
				$batch = [];
			}
			$processed++;
		}

		// Procesar remanente
		if (!empty($batch)) {
			$results = $this->processBatch($batch, $slug);
			$inserted += $results['ins'];
			$updated  += $results['upd'];
			$ignored  += $results['ign'];
		}

		fclose($handle);

		return [
			'total'    => $processed,
			'inserted' => $inserted,
			'updated'  => $updated,
			'ignored'  => $ignored
		];
	}

	/**
	 * Procesa un bloque de registros usando lógica de comparación eficiente.
	 * Soporta INSERT de nuevos registros, UPDATE de activos y REACTIVACIÓN si stt < 501.
	 */
	private function processBatch(array $batch, string $slug): array
	{
		$idSrcs = array_map(fn($item) => $item['id_src'], $batch);
			
		// 1. Obtener estados actuales de los registros existentes para este slug
		$existing = $this->conn->fetchAllAssociative(
			"SELECT id_src, stt FROM item_pub WHERE slug = ? AND id_src IN (?)",
			[$slug, $idSrcs],
			[\Doctrine\DBAL\ParameterType::STRING, Connection::PARAM_STR_ARRAY]
		);

		$statusMap = [];
		foreach ($existing as $ex) {
			$statusMap[$ex['id_src']] = (int) $ex['stt'];
		}

		$ins = 0;
		$upd = 0;
		$ign = 0;

		$this->conn->beginTransaction();
		try {
			foreach ($batch as $data) {

				$idSrc = $data['id_src'];
				if (!isset($statusMap[$idSrc])) {
					// INSERT: Registro nuevo
					$this->conn->insert('item_pub', $data);
					$ins++;
				} else {
					// UPDATE: Si el registro existente estaba activo (< 501) O si viene con status activo (< 501) para reactivarse
					if ($statusMap[$idSrc] < 501 || $data['stt'] < 501) {
						$this->conn->update('item_pub', $data, [
							'id_src' => $idSrc,
							'slug'   => $slug
						]);
						$upd++;
					} else {
						// IGNORAR: Ambos son inactivos/borrados (stt >= 501)
						$ign++;
					}
				}
			}
			$this->conn->commit();
		} catch (\Exception $e) {
			$this->conn->rollBack();
			throw $e;
		}

		return ['ins' => $ins, 'upd' => $upd, 'ign' => $ign];
	}

	/**
	 * Exportación temporal administrativa de inventario exclusivamente por empresa.
	 * 
	 * Requisitos:
	 * 1. Exporta los 29 campos completos de item_pub.
	 * 2. Sin exclusión de stt, is_active ni fuente (incluye pausadas, eliminadas y editadas).
	 * 3. Escritura incremental mediante cursor unbuffered DBAL en archivo temporal .tmp.
	 * 4. Rename atómico a .csv con identificador único no predecible.
	 * 5. Protege /public_html/inv/admin_export/ con .htaccess denegando acceso HTTP directo.
	 * 6. No expone URL de descarga (el archivo permanece en el servidor).
	 *
	 * @throws \InvalidArgumentException Si el slug es inválido.
	 * @throws \RuntimeException Si ocurre un fallo de I/O o base de datos.
	 */
	public function exportAdminTemporalCsv(string $slug): array
	{
		$trimmedSlug = trim($slug);
		if ($trimmedSlug === '' || $trimmedSlug === 'Todas las empresas' || !preg_match('/^[a-zA-Z0-9_-]+$/', $trimmedSlug)) {
			throw new \InvalidArgumentException('El parámetro slug es obligatorio y debe contener un identificador alfanumérico válido.');
		}

		$adminExportDir = rtrim($this->invExp, '/\\') . '/admin_export/';
		if (!is_dir($adminExportDir)) {
			if (!@mkdir($adminExportDir, 0755, true) && !is_dir($adminExportDir)) {
				throw new \RuntimeException("No fue posible crear el directorio de exportación administrativa: {$adminExportDir}");
			}
		}

		// Asegurar protección HTTP mediante .htaccess si no existe
		$htaccessPath = $adminExportDir . '.htaccess';
		if (!file_exists($htaccessPath)) {
			$htaccessContent = "# Denegar acceso HTTP directo a archivos temporales de exportacion administrativa\n"
				. "<IfModule mod_authz_core.c>\n"
				. "    Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "    Order Deny,Allow\n"
				. "    Deny from all\n"
				. "</IfModule>\n";
			@file_put_contents($htaccessPath, $htaccessContent);
		}

		$sql = "SELECT id, slug, type, stt, id_src, iku, src, fuente, thumb, img_big, 
				       price, costo, link, is_active, pieza, mrk_id, mdl_id, anio_inicio, 
				       anio_fin, lado, poss, detalles, variantes, extras, wa_id, ta_id, 
				       from_dev, created, updated_at 
				FROM item_pub 
				WHERE slug = :slug 
				ORDER BY id ASC";

		$result = $this->conn->iterateAssociative($sql, ['slug' => $trimmedSlug]);

		$cleanSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $trimmedSlug);
		$timestamp = date('Ymd_His');
		$randomToken = bin2hex(random_bytes(8));
		$baseName = sprintf('tmp_inv_%s_%s_%s', $cleanSlug, $timestamp, $randomToken);
		$finalFilename = $baseName . '.csv';
		$tempFilename = '.' . $baseName . '.tmp';
		$tempPath = $adminExportDir . $tempFilename;
		$finalPath = $adminExportDir . $finalFilename;

		$handle = @fopen($tempPath, 'w');
		if (!$handle) {
			throw new \RuntimeException("No se pudo inicializar la escritura del archivo temporal en: {$tempPath}");
		}

		// BOM UTF-8 para garantizar codificación adecuada en lectores CSV
		fwrite($handle, "\xEF\xBB\xBF");

		// Encabezado con los 29 campos exactos de ItemPub
		$headers = [
			'id', 'slug', 'type', 'stt', 'id_src', 'iku', 'src', 'fuente', 'thumb', 'img_big',
			'price', 'costo', 'link', 'is_active', 'pieza', 'mrk_id', 'mdl_id', 'anio_inicio',
			'anio_fin', 'lado', 'poss', 'detalles', 'variantes', 'extras', 'wa_id', 'ta_id',
			'from_dev', 'created', 'updated_at'
		];
		fputcsv($handle, $headers);

		$count = 0;
		try {
			foreach ($result as $row) {
				// Formatear booleano
				$row['is_active'] = !empty($row['is_active']) ? '1' : '0';

				// Formatear extras garantizando texto JSON
				if (isset($row['extras']) && (is_array($row['extras']) || is_object($row['extras']))) {
					$row['extras'] = json_encode($row['extras'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}

				$orderedRow = [];
				foreach ($headers as $col) {
					$val = $row[$col] ?? null;
					$orderedRow[] = $val !== null ? (string)$val : '';
				}

				fputcsv($handle, $orderedRow);
				$count++;
			}
		} catch (\Throwable $e) {
			fclose($handle);
			if (file_exists($tempPath)) {
				@unlink($tempPath);
			}
			throw new \RuntimeException("Fallo durante la escritura incremental del CSV de inventario: " . $e->getMessage(), 0, $e);
		}

		fclose($handle);

		if (!file_exists($tempPath)) {
			throw new \RuntimeException("El archivo temporal generado desapareció antes de consolidar.");
		}

		$bytes = filesize($tempPath);

		// Rename atómico a nombre definitivo
		if (!@rename($tempPath, $finalPath)) {
			if (file_exists($tempPath)) {
				@unlink($tempPath);
			}
			throw new \RuntimeException("Fallo al finalizar de forma atómica el archivo de exportación: {$finalFilename}");
		}

		return [
			'fileId' => $finalFilename,
			'filename' => $finalFilename,
			'slug' => $trimmedSlug,
			'total' => $count,
			'bytes' => $bytes,
			'status' => 'completed',
			'generatedAt' => date('c'),
		];
	}

}


