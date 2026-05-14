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
		$sql = "SELECT id, type, stt, id_src, iku, src, title, thumb, img_big, price, costo, link, is_active, pieza, mrk_id, mdl_id, anio_inicio, anio_fin, lado, poss, detalles, extras, wa_id, ta_id, created 
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
			'id', 'type', 'stt', 'id_src', 'iku', 'src', 'title', 'thumb', 'img_big', 
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
				'type'       => (int) $row[1],
				'stt'        => (int) $row[2],
				'id_src'     => $row[3],
				'iku'        => $row[4],
				'src'        => $row[5],
				'title'      => $row[6],
				'thumb'      => $row[7],
				'img_big'    => $row[8],
				'price'      => (float) $row[9],
				'costo'      => (float) $row[10],
				'link'       => $row[11],
				'is_active'  => (int) ($row[12] === 'true' || $row[12] === '1'),
				'pieza'      => $row[13],
				'mrk_id'     => (int) $row[14],
				'mdl_id'     => (int) $row[15],
				'anio_inicio'=> (int) $row[16],
				'anio_fin'   => (int) $row[17],
				'lado'       => $row[18],
				'poss'       => $row[19],
				'detalles'   => $row[20],
				'extras'     => $row[21], // Asumimos JSON string o vacío
				'wa_id'      => $row[22],
				'ta_id'      => (int) $row[23],
				'slug'       => $slug,
				'created'    => $row[24] ?? $now,
				'updated_at' => $now
			];

			if (count($batch) >= $this->batchSize) {
				$results = $this->processBatch($batch);
				$inserted += $results['ins'];
				$updated  += $results['upd'];
				$ignored  += $results['ign'];
				$batch = [];
			}
			$processed++;
		}

		// Procesar remanente
		if (!empty($batch)) {
			$results = $this->processBatch($batch);
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
	 */
	private function processBatch(array $batch): array
	{
		$idSrcs = array_map(fn($item) => $item['id_src'], $batch);
			
		// 1. Obtener estados actuales de los registros existentes
		$existing = $this->conn->fetchAllAssociative(
			"SELECT id_src, stt FROM item_pub WHERE id_src IN (?)",
			[$idSrcs],
			[Connection::PARAM_STR_ARRAY]
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
					// INSERT
					$this->conn->insert('item_pub', $data);
					$ins++;
				} else {
					// Existe: Verificar status
					if ($statusMap[$idSrc] < 501) {
						// UPDATE
						$this->conn->update('item_pub', $data, ['id_src' => $idSrc]);
						$upd++;
					} else {
						// IGNORAR (Marcado como borrado)
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

}
