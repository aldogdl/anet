<?php

namespace App\Repository;

use App\Entity\ItemPub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemPub>
 *
 * @method ItemPub|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemPub|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemPub[]    findAll()
 * @method ItemPub[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemPubRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ItemPub::class);
	}

	/** */
	public function existPub(String $idSrc): int
	{
		$dql = 'SELECT COUNT(it.id) FROM ' . ItemPub::class . ' it '.
		'WHERE it.idSrc = :idSrc';

		return $this->_em->createQuery($dql)
			->setParameter('idSrc', $idSrc)->getSingleScalarResult();
	}

	/** */
	public function getIfExistPubById(int $id): ItemPub | null
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '.
		'WHERE it.id = :id';

		return $this->_em->createQuery($dql)
			->setParameter('id', $id)->getOneOrNullResult();
	}

	/** */
	public function getIfExistPubByIdToArray(int $id): array
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '.
		'WHERE it.id = :id';

		return $this->_em->createQuery($dql)
			->setParameter('id', $id)->getArrayResult();
	}

	/**
	 * Obtiene el ItemPub completo por su idSrc en formato array asociativo,
	 * con opción de validar pertenencia por slug.
	 */
	public function getPubByIdSrcToArray(string $idSrc, string $slug = ''): ?array
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it ' .
			'WHERE it.idSrc = :idSrc';
		$params = ['idSrc' => $idSrc];

		if (!empty($slug)) {
			$dql .= ' AND it.slug = :slug';
			$params['slug'] = $slug;
		}

		$results = $this->_em->createQuery($dql)
			->setParameters($params)
			->setMaxResults(1)
			->getArrayResult();

		if (empty($results)) {
			return null;
		}

		$item = $results[0];
		if (isset($item['extras']) && is_string($item['extras'])) {
			$item['extras'] = json_decode($item['extras'], true) ?? [];
		}

		return $item;
	}

	/** */
	public function getPubsBySlug(string $slug, string $waId): \Doctrine\ORM\Query
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '.
		'WHERE it.slug = :slug AND it.waId != :waId AND it.isActive = 1 '.
		'ORDER BY it.updatedAt DESC, it.id DESC';

		return $this->_em->createQuery($dql)
			->setParameter('slug', $slug)
			->setParameter('waId', $waId);
	}

	/** */
	public function getAllItemsByIds(string $slug, array $ids): array
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '
			. 'WHERE it.slug = :slug '
			. 'AND it.id IN (:ids)'
			. 'ORDER BY it.updatedAt DESC, it.id DESC';

		return $this->_em->createQuery($dql)
			->setParameters(['slug' => $slug, 'ids' => $ids])
			->getArrayResult();
	}

	/** */
	public function getAllMsgAfterUpdate(string $slug, String $waId, int $lastUpdate, string $fromDev = ''): array
	{
		$moreQuery = '';
		$parameters = ['slug' => $slug];
    
		if(!empty($fromDev)) {
			$moreQuery .= 'AND (it.waId != :waId OR it.fromDev != :dev) ';
			$parameters['waId'] = $waId;
			$parameters['dev'] = $fromDev;
		} else {
			$moreQuery .= 'AND it.waId != :waId ';
			$parameters['waId'] = $waId;
		}

		if(is_int($lastUpdate) && $lastUpdate > 0) {

			$safeLastUpdate = $lastUpdate;
			$updatedAt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $safeLastUpdate / 1000));
			if ($updatedAt === false) {
				$updatedAt = new \DateTimeImmutable('@' . floor($safeLastUpdate / 1000));
			}

			$moreQuery .= 'AND it.updatedAt >= :updatedAt ';
			$tz = new \DateTimeZone(date_default_timezone_get()); 
			$updatedAt = $updatedAt->setTimezone($tz);
			$parameters['updatedAt'] = $updatedAt;
		}

		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '
			. 'WHERE it.slug = :slug '
			. $moreQuery
			. 'ORDER BY it.updatedAt DESC, it.id DESC';

		$items = $this->_em->createQuery($dql)
			->setParameters($parameters)
			->getArrayResult();

		$results = [];
		$pendings = [];
		$inactives = [];
		$last = $lastUpdate;

		foreach ($items as $index => $item) {

			$updatedAtValue = $item['updatedAt'];
			if ($updatedAtValue instanceof \DateTimeInterface) {
				$updatedAtMillis = (int) floor((float) $updatedAtValue->format('U.u') * 1000);
			} else {
				// Si viene como "2026-04-21 07:20:12.500000"
				$obj = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $updatedAtValue);
				if (!$obj) $obj = new \DateTimeImmutable($updatedAtValue);
				$updatedAtMillis = (int) floor((float) $obj->format('U.u') * 1000);
			}

			if ($updatedAtMillis > $last) {
				$last = $updatedAtMillis;
			}

      $isInactive = false;
			// Separar items inactivos o con status 501
			if ((!isset($item['isActive']) || $item['isActive'] == 0) || (isset($item['stt']) && $item['stt'] == 501)) {
				$isInactive = true;
			}
			if($isInactive) {
        $inactives[] = [
					'id' => $item['id'],
					'idSrc' => $item['idSrc'],
					'iku' => $item['iku'],
					'src' => $item['src'],
					'stt' => $item['stt'],
					'isActive' => $item['isActive'],
				];
			} else {
				if ($index < 5) {
					$results[] = $item;
				} else {
					$pendings[] = $item['id'];
				}
			}
		}

		$rota = count($pendings);
		return [
			'results' => $results,
			'pendings' => ($rota > 100) ? [] : $pendings,
			'inactives' => $inactives,
			'last' => $last + 1000,
		];
	}

	/** */
	public function matchOne(array $data): array
	{
		$anioActual = (int) date('Y');

		$mrkId      = $data['mrkId'];
		$mdlId      = $data['mdlId'];
		$anioInicio = (int) $data['anioInicio'];
		$anioFin    = isset($data['anioFin']) ? (int) $data['anioFin']	: $anioInicio + 1;
		$waId       = $data['waId'];
		$slug    = isset($data['slug']) ? $data['slug']	: '';
		$inTo    = isset($data['inTo']) ? $data['inTo']	: '';
		$lado    = isset($data['lado']) ? $data['lado']	: '';
		$poss    = isset($data['poss']) ? $data['poss']	: '';
	
		if ($anioFin > $anioActual) {
			$anioFin = $anioActual;
		}

		$params = [
			'mrkId'      => (int) $mrkId,
			'mdlId'      => (int) $mdlId,
			'isActive'   => 1,
			'anioInicio' => $anioInicio,
			'anioFin'    => $anioFin,
		];

		$dql = "
			SELECT partial it.{id, stt, pieza, lado, poss, price, costo, thumb, fuente, src, idSrc, iku, waId, taId, slug, created, anioInicio, anioFin}
			FROM " . ItemPub::class . " it
			WHERE it.mrkId = :mrkId
				AND it.mdlId = :mdlId
				AND it.isActive = :isActive
				AND it.slug IS NOT NULL
				AND it.slug != ''
				AND it.anioInicio <= :anioFin
				AND it.anioFin >= :anioInicio
			";

		if (!empty($waId)) {
			$params['waId'] = $waId;
			if (!empty($inTo)) {
				$dql .= " AND it.waId = :waId";
			} else {
				$dql .= " AND it.waId != :waId";
			}
		}

		if (!empty($slug)) {
			$params['slug'] = $slug;
			if (!empty($inTo)) {
				$dql .= " OR it.slug = :slug";
			} else {
				$dql .= " AND it.slug != :slug";
			}
		}

		if (!empty($lado)) {
			$dql .= " AND it.lado IN (:lado, 'A')";
			$params['lado'] = $lado;
		}

		if (!empty($poss)) {
			$dql .= " AND it.poss IN (:poss, 'A')";
			$params['poss'] = $poss;
		}

		$dql .= " ORDER BY it.created DESC, it.id DESC";
		return $this->_em->createQuery($dql)
			->setParameters($params)
			->getArrayResult();
	}

	/** */
	public function setPub(array $data, String $pathDicc): array
	{

		$action = 'add';
		$lado = '';
		$poss = '';
    $replace = '__ID__';

		$obj = null;
    if($data['id'] ?? 0 != 0) {
			$obj = $this->getIfExistPubById($data['id']);
		}

		if($obj == null) {
			$obj = new ItemPub();
			$obj = $obj->fromJson($data);
		}else{
			$action = 'edt';
			if(mb_strpos($data['link'], $replace) !== false) {
				$data['link'] = str_replace($replace, (string) $obj->getId(), $data['link']);
			}
			$obj = $obj->updateFromJson($data);
		}

    $dicc = json_decode(file_get_contents($pathDicc), true);

		if(isset($data['lado'])) {

			$lado = mb_strtolower($data['lado']);
			if(array_key_exists($lado, $dicc['lp_encode'])) {
				$lado = $dicc['lp_encode'][$lado];
			} else {
				$lado = mb_strtoupper($data['lado']);
				if(!array_key_exists($lado, $dicc['lp_decode'])) {
					$lado = 'A';
				}
			}
			if($lado != '') {
				$obj->setLado($lado);
			}
		}

		if(isset($data['poss'])) {
			$poss = mb_strtolower($data['poss']);
			if(array_key_exists($poss, $dicc['lp_encode'])) {
				$poss = $dicc['lp_encode'][$poss];
			} else {
				$poss = mb_strtoupper($data['poss']);
				if(!array_key_exists($poss, $dicc['lp_decode'])) {
					$poss = 'A';
				}
			}
			if($poss != '') {
				$obj->setPoss($poss);
			}
		}

		try {
			$this->_em->persist($obj);
			$this->_em->flush();
			$id = $obj->getId();
			if(mb_strpos($obj->getLink(), $replace) !== false) {
				$obj->setLink(str_replace($replace, (string) $id, $obj->getLink()));
				$this->_em->persist($obj);
				$this->_em->flush();
			}
			return ['abort' => false, 'action' => $action, 'body' => ['id' => $id]];
		} catch (\Throwable $th) {
			return ['abort' => true, 'action' => 'error', 'body' => $th->getMessage()];
		}

	}

	/** 
	 * Pausa publicaciones para que deje de aparecer en el catálogo.
	 * Actualiza el item con stt = 501, isActive = false y updatedAt = ahora
	*/
	public function pausarPubByIdSrc(array $ids, string $waId, string $dev): array
	{

		try {
			$dql = 'UPDATE ' . ItemPub::class . ' it '.
			'SET it.stt = 501, it.isActive = false, it.updatedAt = :updatedAt, '.
			'it.waId = :waId, it.fromDev = :dev '.
			'WHERE it.idSrc IN (:ids)';

			$result = $this->_em->createQuery($dql)
				->setParameters([
					'ids' => $ids,
					'waId' => $waId,
					'dev' => $dev,
					'updatedAt' => new \DateTimeImmutable()
				])
				->execute();

			return ['success' => true, 'rowsAffected' => $result];
		} catch (\Throwable $th) {
			return ['success' => false, 'error' => $th->getMessage()];
		}
	}

	/** 
	 * Pausa la publicación para que deje de aparecer en el catálogo.
	 * Actualiza el item con stt = 501, isActive = false y updatedAt = ahora
	*/
	public function pausarPub(int $id, string $waId, string $dev): array
	{
		try {
			$dql = 'UPDATE ' . ItemPub::class . ' it '.
			'SET it.stt = 501, it.isActive = false, it.updatedAt = :updatedAt, '.
			'it.waId = :waId, it.fromDev = :dev '.
			'WHERE it.id = :id';

			$result = $this->_em->createQuery($dql)
				->setParameters([
					'id' => $id,
					'waId' => $waId,
					'dev' => $dev,
					'updatedAt' => new \DateTimeImmutable()
				])
				->execute();

			return ['success' => true, 'rowsAffected' => $result];
		} catch (\Throwable $th) {
			return ['success' => false, 'error' => $th->getMessage()];
		}
	}

	/** 
	 * Elimina items pausados (stt = 501) cuyo updatedAt sea mayor a 5 días.
	 * Retorna array con slug e iku antes de eliminarlos para limpiar imágenes.
	*/
	public function deleteOldPausedItems(): array
	{
		try {
			// Calcular fecha hace 5 días
			$fiveDaysAgo = new \DateTimeImmutable('now - 5 days');

			// Obtener items a eliminar con su slug e iku
			$dql = 'SELECT it.id, it.slug, it.iku FROM ' . ItemPub::class . ' it '.
			'WHERE it.stt = 501 AND it.updatedAt < :fiveDaysAgo '.
			'ORDER BY it.updatedAt ASC';

			$itemsToDelete = $this->_em->createQuery($dql)
				->setParameter('fiveDaysAgo', $fiveDaysAgo)
				->getArrayResult();

			// Preparar lista con slug e iku para eliminar imágenes
			$imageData = [];
			$ids = [];
			foreach ($itemsToDelete as $item) {
				$ids[] = $item['id'];
				$imageData[] = [
					'slug' => $item['slug'],
					'iku' => $item['iku']
				];
			}

			// Eliminar los items usando los IDs ya recopilados
			if (!empty($ids)) {
				$dql = 'DELETE FROM ' . ItemPub::class . ' it '.
				'WHERE it.id IN (:ids)';

				$rowsDeleted = $this->_em->createQuery($dql)
					->setParameter('ids', $ids)
					->execute();

				return [
					'success' => true,
					'rowsDeleted' => $rowsDeleted,
					'imageData' => $imageData
				];
			}

			return [
				'success' => true,
				'rowsDeleted' => 0,
				'imageData' => []
			];
		} catch (\Throwable $th) {
			return ['success' => false, 'error' => $th->getMessage()];
		}
	}

	/** */
	public function delPub(int $id, string $waId): int
	{
		$dql = 'DELETE FROM ' . ItemPub::class . ' it '.
		'WHERE it.id = :id AND it.waId = :waId';

		return $this->_em->createQuery($dql)
			->setParameters(['id' => $id, 'waId' => $waId])
			->execute();
	}
  
	/** */
	public function updateImagePath(int $idItem, String $thubn, String $pathImg): string
	{

    $item = $this->getIfExistPubById($idItem);
		if($item == null) {
			return 'No se encontró el item con id: ' . $idItem;
		}
		try {
			$item->setPathImg($pathImg);
			$item->setThumb($thubn);
			$this->_em->persist($item);
			$this->_em->flush();
			return 'Ruta de imagen actualizada correctamente';
		} catch (\Throwable $th) {
			return 'Error al actualizar la ruta de imagen: ' . $th->getMessage();
		}
	}

	/** 
	 * Cuenta la cantidad total de registros en ItemPub para un slug específico.
	 * Consulta ultra-ligera O(1) sobre índice.
	 */
	public function countIdSrcsBySlug(string $slug): int
	{
		$dql = 'SELECT COUNT(it.id) FROM ' . ItemPub::class . ' it WHERE it.slug = :slug';
		return (int) $this->_em->createQuery($dql)
			->setParameter('slug', $slug)
			->getSingleScalarResult();
	}

	/** 
	 * Obtiene el manifiesto ultra-ligero de idSrcs por lotes usando paginación por cursor (Keyset Pagination O(1)).
	 * Retorna id, idSrc, iku, stt, isActive y src.
	 * Si $lastId es proporcionado, filtra por it.id < $lastId ordenado DESC.
	 */
	public function getManifestBySlugPaged(string $slug, ?int $lastId = null, int $limit = 1000): array
	{
		$dql = 'SELECT it.id, it.idSrc, it.iku, it.stt, it.isActive, it.src FROM ' . ItemPub::class . ' it '
			. 'WHERE it.slug = :slug ';

		$params = ['slug' => $slug];

		if ($lastId !== null && $lastId > 0) {
			$dql .= 'AND it.id < :lastId ';
			$params['lastId'] = $lastId;
		}

		$dql .= 'ORDER BY it.id DESC';

		return $this->_em->createQuery($dql)
			->setParameters($params)
			->setMaxResults($limit)
			->getArrayResult();
	}

	/**
	 * CALL VPS
	 * Desactiva un ItemPub por su idSrc y slug estableciendo isActive = false y stt = 501.
	 * Retorna null si no existe, o un array con id, iku e idSrc si fue desactivado.
	 */
	public function deactivateByIdSrc(string $idSrc, string $slug = ''): ?array
	{
		$criteria = ['idSrc' => $idSrc];
		if (!empty($slug)) {
			$criteria['slug'] = $slug;
		}

		$item = $this->findOneBy($criteria);
		if (!$item) {
			return null;
		}

		$currentStt = $item->getStt();
		$extras = $item->getExtras() ?? [];
		if (is_string($extras)) {
			$extras = json_decode($extras, true) ?? [];
		}

		// Si stt != 501, guardar previousStt; si ya estaba en 501, no sobrescribir
		if ($currentStt !== 501) {
			$extras['previousStt'] = $currentStt;
			$item->setExtras($extras);
		}

		$item->setIsActive(false);
		$item->setStt(501);
		$item->setUpdatedAt(new \DateTimeImmutable('now'));

		$this->_em->persist($item);
		$this->_em->flush();

		return [
			'id' => $item->getId(),
			'iku' => $item->getIku(),
			'idSrc' => $item->getIdSrc(),
		];
	}

	/**
	 * CALL VPS
	 * Actualiza únicamente los campos simples de un ItemPub por su idSrc y slug.
	 * Campos permitidos: price, isActive, link, partNumber.
	 * Retorna el array completo del ItemPub actualizado, o null si no existe.
	 */
	public function updateSimpleByIdSrc(string $idSrc, string $slug, array $changes): ?array
	{
		$criteria = ['idSrc' => $idSrc];
		if (!empty($slug)) {
			$criteria['slug'] = $slug;
		}

		$item = $this->findOneBy($criteria);
		if (!$item) {
			return null;
		}

		// 1. price
		if (array_key_exists('price', $changes) && is_numeric($changes['price'])) {
			$item->setPrice((float)$changes['price']);
		}

		// 2. isActive (reactivación)
		if (array_key_exists('isActive', $changes)) {
			$newActive = filter_var($changes['isActive'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			if ($newActive !== null) {
				$item->setIsActive($newActive);
				if ($newActive) {
					$extras = $item->getExtras() ?? [];
					if (is_string($extras)) {
						$extras = json_decode($extras, true) ?? [];
					}
					// Si extras['previousStt'] existe y es válido, restaurar ese valor en stt y eliminar la clave
					if (isset($extras['previousStt']) && is_numeric($extras['previousStt'])) {
						$item->setStt((int)$extras['previousStt']);
						unset($extras['previousStt']);
						$item->setExtras($extras);
					}
				}
			}
		}

		// 3. link
		if (array_key_exists('link', $changes) && is_string($changes['link'])) {
			$item->setLink(trim($changes['link']));
		}

		// 4. partNumber (en extras['numPart'])
		if (array_key_exists('partNumber', $changes)) {
			$extras = $item->getExtras() ?? [];
			if (is_string($extras)) {
				$extras = json_decode($extras, true) ?? [];
			}
			$extras['numPart'] = trim((string)$changes['partNumber']);
			$item->setExtras($extras);
		}

		// 5. thumb
		if (array_key_exists('thumb', $changes) && $changes['thumb'] !== null) {
			$item->setThumb(trim((string)$changes['thumb']));
		}

		// 6. imgBig
		if (array_key_exists('imgBig', $changes) && $changes['imgBig'] !== null) {
			$item->setImgBig(trim((string)$changes['imgBig']));
		}

		// 7. Merge de extras (conservando las demás claves existentes y reemplazando únicamente pictures y pathImg)
		if (array_key_exists('extras', $changes) && is_array($changes['extras'])) {
			$extras = $item->getExtras() ?? [];
			if (is_string($extras)) {
				$extras = json_decode($extras, true) ?? [];
			}
			if (array_key_exists('pictures', $changes['extras'])) {
				$extras['pictures'] = $changes['extras']['pictures'];
			}
			if (array_key_exists('pathImg', $changes['extras'])) {
				$extras['pathImg'] = (string)$changes['extras']['pathImg'];
			}
			$item->setExtras($extras);
		}

		$item->setUpdatedAt(new \DateTimeImmutable('now'));

		$this->_em->persist($item);
		$this->_em->flush();

		return $this->getPubByIdSrcToArray($idSrc, $slug);
	}

	/**
	 * CALL VPS
	 * Actualiza atómicamente un ItemPub por su idSrc y slug con cambios estructurales
	 * y simples combinados en un solo UPDATE.
	 *
	 * - Aplica campos estructurales: fuente, pieza, mrkId, mdlId, anioInicio, anioFin, lado, poss, detalles, stt.
	 * - Aplica campos simples (si están presentes): price, isActive, link.
	 * - Realiza merge sobre extras: actualiza mk, md, calif, numPart; elimina cbi;
	 *   preserva idSr, pictures, pathImg y cualquier otra clave existente.
	 * - Retorna el array completo del ItemPub actualizado o null si no existe.
	 */
	public function updateStructuralByIdSrc(string $idSrc, string $slug, array $changes): ?array
	{
		$criteria = ['idSrc' => $idSrc];
		if (!empty($slug)) {
			$criteria['slug'] = $slug;
		}

		$item = $this->findOneBy($criteria);
		if (!$item) {
			return null;
		}

		// 1. Campos estructurales
		if (array_key_exists('fuente', $changes)) {
			$item->setFuente(trim((string)$changes['fuente']));
		}
		if (array_key_exists('pieza', $changes)) {
			$item->setPieza(trim((string)$changes['pieza']));
		}
		if (array_key_exists('mrkId', $changes)) {
			$item->setMrkId((int)$changes['mrkId']);
		}
		if (array_key_exists('mdlId', $changes)) {
			$item->setMdlId((int)$changes['mdlId']);
		}
		if (array_key_exists('anioInicio', $changes)) {
			$item->setAnioInicio((int)$changes['anioInicio']);
		}
		if (array_key_exists('anioFin', $changes)) {
			$item->setAnioFin(isset($changes['anioFin']) ? (int)$changes['anioFin'] : null);
		}
		if (array_key_exists('lado', $changes)) {
			$item->setLado($changes['lado'] !== null ? trim((string)$changes['lado']) : null);
		}
		if (array_key_exists('poss', $changes)) {
			$item->setPoss($changes['poss'] !== null ? trim((string)$changes['poss']) : null);
		}
		if (array_key_exists('detalles', $changes)) {
			$item->setDetalles($changes['detalles'] !== null ? trim((string)$changes['detalles']) : null);
		}
		if (array_key_exists('stt', $changes) && is_numeric($changes['stt'])) {
			$item->setStt((int)$changes['stt']);
		}

		// Imágenes
		if (array_key_exists('thumb', $changes) && $changes['thumb'] !== null) {
			$item->setThumb(trim((string)$changes['thumb']));
		}
		if (array_key_exists('imgBig', $changes) && $changes['imgBig'] !== null) {
			$item->setImgBig(trim((string)$changes['imgBig']));
		}

		// 2. Campos simples adicionales
		if (array_key_exists('price', $changes) && is_numeric($changes['price'])) {
			$item->setPrice((float)$changes['price']);
		}
		if (array_key_exists('isActive', $changes)) {
			$newActive = filter_var($changes['isActive'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			if ($newActive !== null) {
				$item->setIsActive($newActive);
			}
		}
		if (array_key_exists('link', $changes) && is_string($changes['link'])) {
			$item->setLink(trim($changes['link']));
		}

		// 3. Merge selectivo sobre extras
		$extras = $item->getExtras() ?? [];
		if (is_string($extras)) {
			$extras = json_decode($extras, true) ?? [];
		}

		if (array_key_exists('extras', $changes) && is_array($changes['extras'])) {
			foreach ($changes['extras'] as $k => $v) {
				if ($k === 'cbi') {
					continue; // cbi no debe incorporarse
				}
				$extras[$k] = $v;
			}
		}

		// Eliminar explícitamente cbi ante cambio estructural
		unset($extras['cbi']);

		$item->setExtras($extras);
		$item->setUpdatedAt(new \DateTimeImmutable('now'));

		$this->_em->persist($item);
		$this->_em->flush();

		return $this->getPubByIdSrcToArray($idSrc, $slug);
	}

}

