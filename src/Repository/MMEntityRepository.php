<?php

namespace App\Repository;

use App\Entity\MMEntity;
use App\Entity\ItemPub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MMEntity>
 *
 * @method MMEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method MMEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method MMEntity[]    findAll()
 * @method MMEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MMEntityRepository extends ServiceEntityRepository
{

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, MMEntity::class);
	}

	/** */
	public function getById(int $id) : \Doctrine\ORM\Query
	{
		$dql = 'SELECT m FROM '. MMEntity::class .' m '.
		'WHERE m.id = :id';
		return $this->_em->createQuery($dql)->setParameter('id', $id);
	}
	
	/** 
	 * Revisamos si existe un elemento por medio de su nombre
	*/
	public function existByName(String $name) : bool
	{
		$dql = 'SELECT COUNT(m.id) FROM '. MMEntity::class .' m '.
		'WHERE m.name = :name';

		return $this->_em->createQuery($dql)
			->setParameter('name', $name)->getSingleScalarResult() > 0;
	}

	/** 
	 * Revisamos si existe un elemento por medio de su nombre
	*/
	public function getByName(String $name) : \Doctrine\ORM\Query
	{
		$dql = 'SELECT m FROM '. MMEntity::class .' m '.
		'WHERE m.name = :name';

		return $this->_em->createQuery($dql)
			->setParameter('name', $name);
	}

	/** 
	 * Recuperamos todos los elementos de manera slim
	*/
	public function getMMSlim() : array
	{

		$dql = 'SELECT m.id, m.idMrk, m.name, m.variants, m.extras
			FROM ' . MMEntity::class . ' m
			ORDER BY m.name ASC';

		$res = $this->_em->createQuery($dql)
			->getArrayResult();

		$mrks = [];
		$mdls = [];
		$brandNameById = [];

		foreach ($res as $r) {
			$id   = (int)$r['id'];
			$idMrk = (int)$r['idMrk'];

			if ($idMrk === 0) {
				$brandNameById[$id] = $r['name'];

				$mrks[] = [
					'i'  => $id,
					'im' => 0,
					'n'  => $r['name'],
					'v'  => $r['variants'], // si ya es array/json en Doctrine, se queda
					'ex' => $r['extras'],
				];
			} else {
				$mdls[] = [
					'i'  => $id,
					'im' => $idMrk,
					'n'  => $r['name'],
					'nm' => '',              // se llena después
					'v'  => $r['variants'],
					// 'ex' => $r['extras'],  // si en modelos "ya no va", simplemente no lo pongas
				];
    	}
		}

		// 2da pasada: llenar nm
		foreach ($mdls as &$m) {
			$m['nm'] = $brandNameById[$m['im']] ?? '';
		}
		unset($m);

		return ['abort' => false, 'data' => ['mrks' => $mrks, 'mdls' => $mdls]];
	}

	/** 
	 * Recuperamos todos los elementos en caso de que $idMrk == null
	 * se refiere a las marcas, en caso contrario son modelos
	*/
	public function getMM(?int $idMrk) : array
	{
		$dql = 'SELECT m FROM '. MMEntity::class .' m ';
		if($idMrk != null) {
			$dql = $dql . 'WHERE m.idMrk = :idMrk';
		}else{
			$dql = $dql . 'WHERE m.idMrk = 0';
		}

		$dql = $dql . ' ORDER BY m.id ASC';
		if($idMrk != null) {
			return $this->_em->createQuery($dql)
				->setParameter('idMrk', $idMrk)->getArrayResult();
		}

		return $this->_em->createQuery($dql)->getArrayResult();
	}

	/** 
	 * Guardamos el elemento ya sea marca o modelo
	*/
	public function setMM(array $mm) : array
	{
		$id = isset($mm['id']) ? (int)$mm['id'] : 0;
		if ($id > 0) {
			// Es una edición de un elemento existente
			$obj = $this->find($id);
			if (!$obj) {
				return ['abort' => true, 'data' => 'Elemento no encontrado para editar'];
			}
			$obj->fromJson($mm);
		} else {
			// Es un nuevo elemento
			$obj = new MMEntity();
			$obj = $obj->fromJson($mm);
			// Ya que se pretende agregar, buscamos en ls BD
			// si el nombre ya existe para evitar duplicados
			if($this->existByName($obj->getName())) {
				$query = $this->getByName($obj->getName());
				$existingObj = $query->getOneOrNullResult();
				if ($existingObj) {
					return [
						'abort' => true,
						'data' => 'Ya existe un elemento con ese nombre (ID: ' . $existingObj->getId() . ' ' . $existingObj->getName() . ' - ' . ($existingObj->getIdMrk() == 0 ? 'Marca' : 'Modelo') . ')'
					];
				}
				return ['abort' => true, 'data' => 'Ya existe un elemento con ese nombre'];
			}
		}

		try {
			$this->_em->persist($obj);
			$this->_em->flush();
			return ['abort' => false, 'data' => $this->getMMSlim('alls')];
		} catch (\Throwable $th) {
			return ['abort' => true, 'data' => $th->getMessage()];
		}

		return ['abort' => true, 'data' => 'Error inesperado'];
	}

	/** 
	 * Borramos el elemento ya sea marca o modelo
	*/
	public function delMM(int $id) : array
	{
		$obj = $this->find($id);
		if (!$obj) {
			return ['abort' => true, 'data' => 'Elemento no encontrado'];
		}

		if($obj->getIdMrk() == 0) {
			// Si es una marca, borramos también sus modelos relacionados
			$models = $this->getMM($obj->getId());
			foreach ($models as $model) {
				$modelObj = $this->find($model['id']);
				if ($modelObj) {
					$this->_em->remove($modelObj);
				}
			}
		}

		try {
			$this->_em->remove($obj);
			$this->_em->flush();
			return ['abort' => false, 'data' => $this->getMMSlim('alls')];
		} catch (\Throwable $th) {
			return ['abort' => true, 'data' => $th->getMessage()];
		}

		return ['abort' => true, 'data' => 'Error inesperado'];
	}

	/**
	 * Guarda de manera atómica un lote completo de borradores de Marcas y Modelos.
	 *
	 * Garantías:
	 * 1. Validación previa completa: sin mutaciones parciales si hay colisiones,
	 *    nombres vacíos o dependencias de inventario activas.
	 * 2. Transacción Doctrine única: rollback ante cualquier fallo o excepción.
	 * 3. Mapeo de IDs temporales (< 0) hacia IDs definitivos generados en MySQL.
	 * 4. Preservación íntegra de variantes manuales y extras (imágenes).
	 *
	 * @param array $payload Estructura con 'brands' y 'models' (created, updated, deleted)
	 * @return array Resultado con 'abort', 'data' => ['brandIdMap', 'modelIdMap']
	 */
	public function saveDraftAtomic(array $payload): array
	{
		$brandsPayload = $payload['brands'] ?? [];
		$modelsPayload = $payload['models'] ?? [];
		$consolidationsPayload = $payload['consolidations'] ?? [];

		$createdBrands = $brandsPayload['created'] ?? [];
		$updatedBrands = $brandsPayload['updated'] ?? [];
		$deletedBrandIds = array_values(array_unique(array_filter(array_map('intval', $brandsPayload['deleted'] ?? []))));

		$createdModels = $modelsPayload['created'] ?? [];
		$updatedModels = $modelsPayload['updated'] ?? [];
		$deletedModelIds = array_values(array_unique(array_filter(array_map('intval', $modelsPayload['deleted'] ?? []))));

		$rawBrandConsolidations = $consolidationsPayload['brands'] ?? [];
		$rawModelConsolidations = $consolidationsPayload['models'] ?? [];

		// 1. Parsing y validación estructural de consolidaciones
		$brandConsolidationMap = [];
		foreach ($rawBrandConsolidations as $bc) {
			$sId = (int)($bc['sourceId'] ?? 0);
			$tId = (int)($bc['targetId'] ?? 0);
			if ($sId <= 0 || $tId <= 0 || $sId === $tId) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Consolidación de marca inválida: sourceId y targetId deben ser mayores a 0 y distintos (origen: $sId, destino: $tId).",
				];
			}
			if (isset($brandConsolidationMap[$sId])) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Mapeo contradictorio: la marca origen #$sId se intenta consolidar más de una vez.",
				];
			}
			$brandConsolidationMap[$sId] = $tId;
		}

		$modelConsolidationMap = [];
		foreach ($rawModelConsolidations as $mc) {
			$sId = (int)($mc['sourceId'] ?? 0);
			$tId = (int)($mc['targetId'] ?? 0);
			if ($sId <= 0 || $tId <= 0 || $sId === $tId) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Consolidación de modelo inválida: sourceId y targetId deben ser mayores a 0 y distintos (origen: $sId, destino: $tId).",
				];
			}
			if (isset($modelConsolidationMap[$sId])) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Mapeo contradictorio: el modelo origen #$sId se intenta consolidar más de una vez.",
				];
			}
			$modelConsolidationMap[$sId] = $tId;
		}

		// 2. Validación de ciclos, destinos eliminados y equivalencia real en marcas
		foreach ($brandConsolidationMap as $sId => $tId) {
			if (in_array($tId, $deletedBrandIds, true)) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "El destino de consolidación de marca #$tId está marcado para eliminación.",
				];
			}
			if (isset($brandConsolidationMap[$tId])) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Ciclo o cadena no permitida en consolidación de marcas (#$sId -> #$tId).",
				];
			}
			$sourceBrand = $this->find($sId);
			$targetBrand = $this->find($tId);
			if (!$sourceBrand || $sourceBrand->getIdMrk() !== 0 || !$targetBrand || $targetBrand->getIdMrk() !== 0) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Marca origen (#$sId) o destino (#$tId) inexistente o no es una marca válida.",
				];
			}
			if (!$this->_checkEquivalence($sourceBrand, $targetBrand)) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Las marcas '{$sourceBrand->getName()}' (#$sId) y '{$targetBrand->getName()}' (#$tId) no presentan equivalencia real de nombres ni variantes.",
				];
			}
		}

		// 3. Validación de ciclos, destinos eliminados y equivalencia real en modelos
		foreach ($modelConsolidationMap as $sId => $tId) {
			if (in_array($tId, $deletedModelIds, true)) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "El destino de consolidación de modelo #$tId está marcado para eliminación.",
				];
			}
			if (isset($modelConsolidationMap[$tId])) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Ciclo o cadena no permitida en consolidación de modelos (#$sId -> #$tId).",
				];
			}
			$sourceModel = $this->find($sId);
			$targetModel = $this->find($tId);
			if (!$sourceModel || $sourceModel->getIdMrk() === 0 || !$targetModel || $targetModel->getIdMrk() === 0) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Modelo origen (#$sId) o destino (#$tId) inexistente o es una marca.",
				];
			}
			$sBrandId = $sourceModel->getIdMrk();
			$tBrandId = $targetModel->getIdMrk();
			$isSameBrand = ($sBrandId === $tBrandId);
			$isBrandConsolidated = (isset($brandConsolidationMap[$sBrandId]) && $brandConsolidationMap[$sBrandId] === $tBrandId);
			if (!$isSameBrand && !$isBrandConsolidated) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "El modelo '{$sourceModel->getName()}' (#$sId) no puede consolidarse en '{$targetModel->getName()}' (#$tId): pertenecen a marcas distintas sin consolidación activa.",
				];
			}
			if (!$this->_checkEquivalence($sourceModel, $targetModel)) {
				return [
					'abort' => true,
					'code' => 'INVALID_CONSOLIDATION',
					'data' => "Los modelos '{$sourceModel->getName()}' (#$sId) y '{$targetModel->getName()}' (#$tId) no presentan equivalencia real de nombres ni variantes.",
				];
			}
		}

		// 4. Verificación de colisiones entre modelos al consolidar marcas
		foreach ($brandConsolidationMap as $sBrandId => $tBrandId) {
			$sourceBrand = $this->find($sBrandId);
			$targetBrand = $this->find($tBrandId);
			$childModels = $this->findBy(['idMrk' => $sBrandId]);
			$targetModels = $this->findBy(['idMrk' => $tBrandId]);

			$targetModelNames = [];
			foreach ($targetModels as $tm) {
				$targetModelNames[mb_strtolower(trim($tm->getName()))] = $tm->getId();
			}

			foreach ($childModels as $cm) {
				if (isset($modelConsolidationMap[$cm->getId()])) {
					continue; // Este modelo hijo tiene consolidación explícita
				}
				$normName = mb_strtolower(trim($cm->getName()));
				if (isset($targetModelNames[$normName])) {
					return [
						'abort' => true,
						'code' => 'COLLISION_CONFLICT',
						'data' => "Colisión de modelo al consolidar marca: '{$cm->getName()}' (#{$cm->getId()}) ya existe en la marca destino '{$targetBrand->getName()}' (#$tBrandId) y requiere consolidación explícita.",
					];
				}
			}
		}

		// 5. Validaciones previas de nombres de marcas
		$seenBrandNames = [];
		foreach (array_merge($createdBrands, $updatedBrands) as $b) {
			$name = mb_strtolower(trim($b['name'] ?? ''));
			if ($name === '') {
				return [
					'abort' => true,
					'code' => 'INVALID_NAME',
					'data' => 'El nombre de una marca no puede estar vacío.',
				];
			}
			if (isset($seenBrandNames[$name])) {
				return [
					'abort' => true,
					'code' => 'DUPLICATE_NAME',
					'data' => 'Nombre de marca duplicado en el lote: "' . ($b['name'] ?? '') . '".',
				];
			}
			$seenBrandNames[$name] = true;
		}

		// 6. Validaciones previas de nombres de modelos (únicos por marca)
		$seenModelInBrand = [];
		foreach (array_merge($createdModels, $updatedModels) as $m) {
			$name = mb_strtolower(trim($m['name'] ?? ''));
			$mBrandId = isset($m['idMrk']) ? (int)$m['idMrk'] : (isset($m['id_mrk']) ? (int)$m['id_mrk'] : 0);
			if ($name === '') {
				return [
					'abort' => true,
					'code' => 'INVALID_NAME',
					'data' => 'El nombre de un modelo no puede estar vacío.',
				];
			}
			$key = $mBrandId . ':' . $name;
			if (isset($seenModelInBrand[$key])) {
				return [
					'abort' => true,
					'code' => 'DUPLICATE_NAME',
					'data' => 'Nombre de modelo duplicado en la misma marca dentro del lote: "' . ($m['name'] ?? '') . '".',
				];
			}
			$seenModelInBrand[$key] = true;
		}

		// 7. Verificación de dependencias de inventario antes de eliminar (excluyendo fuentes consolidadas)
		$effectiveDeletedModelIds = array_values(array_diff($deletedModelIds, array_keys($modelConsolidationMap)));
		$effectiveDeletedBrandIds = array_values(array_diff($deletedBrandIds, array_keys($brandConsolidationMap)));

		if (!empty($effectiveDeletedModelIds)) {
			$dql = 'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i WHERE i.mdlId IN (:deletedModelIds)';
			$itemCount = (int) $this->_em->createQuery($dql)
				->setParameter('deletedModelIds', $effectiveDeletedModelIds)
				->getSingleScalarResult();
			if ($itemCount > 0) {
				return [
					'abort' => true,
					'code' => 'DEPENDENCY_CONFLICT',
					'data' => "No se pueden eliminar los modelos seleccionados: existen $itemCount publicación(es) de inventario asociadas.",
				];
			}
		}

		if (!empty($effectiveDeletedBrandIds)) {
			// Directo en marcas
			$dql = 'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i WHERE i.mrkId IN (:deletedBrandIds)';
			$brandItemCount = (int) $this->_em->createQuery($dql)
				->setParameter('deletedBrandIds', $effectiveDeletedBrandIds)
				->getSingleScalarResult();
			if ($brandItemCount > 0) {
				return [
					'abort' => true,
					'code' => 'DEPENDENCY_CONFLICT',
					'data' => "No se pueden eliminar las marcas seleccionadas: existen $brandItemCount publicación(es) de inventario asociadas.",
				];
			}

			// Modelos hijos de las marcas a eliminar (excluyendo los que ya se consolidan)
			$dql = 'SELECT m.id FROM ' . MMEntity::class . ' m WHERE m.idMrk IN (:deletedBrandIds)';
			$modelsOfBrands = $this->_em->createQuery($dql)
				->setParameter('deletedBrandIds', $effectiveDeletedBrandIds)
				->getSingleColumnResult();
			$modelsOfBrands = array_values(array_diff($modelsOfBrands, array_keys($modelConsolidationMap)));
			if (!empty($modelsOfBrands)) {
				$dql = 'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i WHERE i.mdlId IN (:modelsOfBrands)';
				$subModelItemCount = (int) $this->_em->createQuery($dql)
					->setParameter('modelsOfBrands', $modelsOfBrands)
					->getSingleScalarResult();
				if ($subModelItemCount > 0) {
					return [
						'abort' => true,
						'code' => 'DEPENDENCY_CONFLICT',
						'data' => "No se pueden eliminar las marcas: modelos pertenecientes a ellas tienen $subModelItemCount publicación(es) de inventario asociadas.",
					];
				}
			}
		}

		// 8. Verificación de existencia para ediciones
		foreach ($updatedBrands as $ub) {
			$id = (int)($ub['id'] ?? 0);
			$entity = $this->find($id);
			if (!$entity || $entity->getIdMrk() !== 0) {
				return [
					'abort' => true,
					'code' => 'NOT_FOUND',
					'data' => "Marca a actualizar no encontrada o inválida (ID: $id).",
				];
			}
		}

		foreach ($updatedModels as $um) {
			$id = (int)($um['id'] ?? 0);
			$entity = $this->find($id);
			if (!$entity || $entity->getIdMrk() === 0) {
				return [
					'abort' => true,
					'code' => 'NOT_FOUND',
					'data' => "Modelo a actualizar no encontrado o inválido (ID: $id).",
				];
			}
		}

		// 9. Ejecución del lote transaccional
		try {
			$brandIdMap = [];
			$modelIdMap = [];

			$txResult = $this->_em->wrapInTransaction(function() use (
				$deletedModelIds,
				$deletedBrandIds,
				$updatedBrands,
				$createdBrands,
				$updatedModels,
				$createdModels,
				$brandConsolidationMap,
				$modelConsolidationMap,
				$effectiveDeletedModelIds,
				$effectiveDeletedBrandIds,
				&$brandIdMap,
				&$modelIdMap
			) {
				// A. Consolidar modelos: fusión de variantes, migración de item_pub y eliminación de fuente
				foreach ($modelConsolidationMap as $sId => $tId) {
					$source = $this->find($sId);
					$target = $this->find($tId);
					if ($source && $target) {
						$mergedVariants = $this->_sanitizeVariants(array_merge(
							$target->getVariants() ?? [],
							$source->getVariants() ?? [],
							[$source->getName()]
						));
						$target->setVariants($mergedVariants);

						// Reasignar publicaciones de inventario hacia el modelo destino
						$this->_em->createQuery('UPDATE ' . ItemPub::class . ' i SET i.mdlId = :targetId WHERE i.mdlId = :sourceId')
							->execute(['targetId' => $tId, 'sourceId' => $sId]);

						$this->_em->remove($source);
					}
				}

				// B. Consolidar marcas: fusión de variantes, traslado de modelos únicos, migración de item_pub y eliminación
				foreach ($brandConsolidationMap as $sId => $tId) {
					$source = $this->find($sId);
					$target = $this->find($tId);
					if ($source && $target) {
						$mergedVariants = $this->_sanitizeVariants(array_merge(
							$target->getVariants() ?? [],
							$source->getVariants() ?? [],
							[$source->getName()]
						));
						$target->setVariants($mergedVariants);

						// Trasladar modelos restantes de la marca origen hacia la marca destino
						$this->_em->createQuery('UPDATE ' . MMEntity::class . ' m SET m.idMrk = :targetId WHERE m.idMrk = :sourceId')
							->execute(['targetId' => $tId, 'sourceId' => $sId]);

						// Reasignar publicaciones de inventario hacia la marca destino
						$this->_em->createQuery('UPDATE ' . ItemPub::class . ' i SET i.mrkId = :targetId WHERE i.mrkId = :sourceId')
							->execute(['targetId' => $tId, 'sourceId' => $sId]);

						$this->_em->remove($source);
					}
				}

				// C. Eliminar modelos regulares
				foreach ($effectiveDeletedModelIds as $mid) {
					$entity = $this->find($mid);
					if ($entity) {
						$this->_em->remove($entity);
					}
				}

				// D. Eliminar marcas regulares y sus modelos en cascada
				foreach ($effectiveDeletedBrandIds as $bid) {
					$brand = $this->find($bid);
					if ($brand) {
						$childModels = $this->findBy(['idMrk' => $bid]);
						foreach ($childModels as $child) {
							$this->_em->remove($child);
						}
						$this->_em->remove($brand);
					}
				}

				// E. Actualizar marcas existentes
				foreach ($updatedBrands as $ub) {
					$entity = $this->find((int)$ub['id']);
					if ($entity) {
						$entity->setName(trim($ub['name']));
						$entity->setVariants($this->_sanitizeVariants($ub['variants'] ?? []));
						if (array_key_exists('extras', $ub)) {
							$entity->setExtras($ub['extras']);
						}
					}
				}

				// F. Crear marcas nuevas y registrar mapeo de tempId
				foreach ($createdBrands as $cb) {
					$tempId = (int)$cb['id'];
					$brand = new MMEntity();
					$brand->setName(trim($cb['name']));
					$brand->setIdMrk(0);
					$brand->setVariants($this->_sanitizeVariants($cb['variants'] ?? []));
					if (array_key_exists('extras', $cb)) {
						$brand->setExtras($cb['extras']);
					}
					$this->_em->persist($brand);
					$this->_em->flush();
					$brandIdMap[(string)$tempId] = $brand->getId();
				}

				// G. Actualizar modelos existentes
				foreach ($updatedModels as $um) {
					$entity = $this->find((int)$um['id']);
					if ($entity) {
						$entity->setName(trim($um['name']));
						$entity->setVariants($this->_sanitizeVariants($um['variants'] ?? []));
						$rawMrkId = isset($um['idMrk']) ? (int)$um['idMrk'] : (isset($um['id_mrk']) ? (int)$um['id_mrk'] : $entity->getIdMrk());
						if ($rawMrkId < 0 && isset($brandIdMap[(string)$rawMrkId])) {
							$rawMrkId = $brandIdMap[(string)$rawMrkId];
						}
						$entity->setIdMrk($rawMrkId);
						if (array_key_exists('extras', $um)) {
							$entity->setExtras($um['extras']);
						}
					}
				}

				// H. Crear modelos nuevos y registrar mapeo de tempId
				foreach ($createdModels as $cm) {
					$tempId = (int)$cm['id'];
					$rawMrkId = isset($cm['idMrk']) ? (int)$cm['idMrk'] : (isset($cm['id_mrk']) ? (int)$cm['id_mrk'] : 0);
					if ($rawMrkId < 0 && isset($brandIdMap[(string)$rawMrkId])) {
						$rawMrkId = $brandIdMap[(string)$rawMrkId];
					}
					if ($rawMrkId <= 0) {
						throw new InvalidArgumentException("El modelo '{$cm['name']}' no tiene una marca padre válida (idMrk: $rawMrkId).");
					}

					$model = new MMEntity();
					$model->setName(trim($cm['name']));
					$model->setIdMrk($rawMrkId);
					$model->setVariants($this->_sanitizeVariants($cm['variants'] ?? []));
					if (array_key_exists('extras', $cm)) {
						$model->setExtras($cm['extras']);
					}
					$this->_em->persist($model);
					$this->_em->flush();
					$modelIdMap[(string)$tempId] = $model->getId();
				}

				// Sincronizar todos los cambios en base de datos antes de la comprobación relacional
				$this->_em->flush();

				// I. Verificación de coherencia relacional final: item_pub.mrk_id == mmentity[item_pub.mdl_id].id_mrk
				$inconsistentCount = (int) $this->_em->createQuery(
					'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i ' .
					'JOIN ' . MMEntity::class . ' m WITH i.mdlId = m.id ' .
					'WHERE i.mdlId > 0 AND i.mrkId != m.idMrk'
				)->getSingleScalarResult();

				if ($inconsistentCount > 0) {
					throw new \RuntimeException("Inconsistencia relacional final en $inconsistentCount publicación(es) de inventario: mrk_id no coincide con id_mrk del modelo asociado.");
				}

				return [
					'abort' => false,
					'data' => [
						'brandIdMap' => $brandIdMap,
						'modelIdMap' => $modelIdMap,
					],
				];
			});

			return $txResult;
		} catch (\Throwable $th) {
			return [
				'abort' => true,
				'code' => 'TX_ERROR',
				'data' => 'Error en la transacción de base de datos: ' . $th->getMessage(),
			];
		}
	}

	/**
	 * Verifica si dos entidades de catálogo presentan equivalencia real.
	 * Comprueba igualdad de nombre canónico normalizado, o que el nombre
	 * de una figure en las variantes de la otra, o coincidencia de variantes.
	 */
	private function _checkEquivalence(MMEntity $source, MMEntity $target): bool
	{
		$sName = mb_strtolower(trim($source->getName() ?? ''));
		$tName = mb_strtolower(trim($target->getName() ?? ''));
		if ($sName !== '' && $sName === $tName) {
			return true;
		}

		$sVars = array_map(fn($v) => mb_strtolower(trim((string)$v)), $source->getVariants() ?? []);
		$tVars = array_map(fn($v) => mb_strtolower(trim((string)$v)), $target->getVariants() ?? []);

		if ($sName !== '' && in_array($sName, $tVars, true)) {
			return true;
		}
		if ($tName !== '' && in_array($tName, $sVars, true)) {
			return true;
		}
		if (!empty(array_intersect($sVars, $tVars))) {
			return true;
		}

		// Coincidencia por normalización alfanumérica o tokens raíz comunes (>= 3 chars)
		$sClean = preg_replace('/[^a-z0-9]/', '', $sName);
		$tClean = preg_replace('/[^a-z0-9]/', '', $tName);
		if ($sClean !== '' && $tClean !== '') {
			if ($sClean === $tClean) {
				return true;
			}
			$sTokens = array_filter(preg_split('/\s+/', $sName), fn($w) => mb_strlen($w) >= 3);
			$tTokens = array_filter(preg_split('/\s+/', $tName), fn($w) => mb_strlen($w) >= 3);
			if (!empty(array_intersect($sTokens, $tTokens))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Limpia y normaliza el arreglo de variantes manuales.
	 */
	private function _sanitizeVariants(mixed $raw): array
	{
		if (!is_array($raw)) {
			return [];
		}
		$clean = [];
		foreach ($raw as $v) {
			if (is_string($v)) {
				$t = trim($v);
				if ($t !== '') {
					$clean[] = $t;
				}
			}
		}
		return array_values(array_unique($clean));
	}


	/**
	 * Prevalida de solo lectura la eliminación o consolidación de una marca o modelo.
	 *
	 * @param array $payload Estructura con sourceId, isBrand, targetId (opcional), draft (opcional)
	 * @return array
	 */
	public function previewDeletionOrConsolidation(array $payload): array
	{
		$sourceId = (int)($payload['sourceId'] ?? 0);
		$isBrand = (bool)($payload['isBrand'] ?? true);
		$targetId = isset($payload['targetId']) && $payload['targetId'] !== null ? (int)$payload['targetId'] : null;
		$draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

		if ($sourceId <= 0) {
			return [
				'abort' => true,
				'code' => 'INVALID_PAYLOAD',
				'data' => 'sourceId debe ser un identificador numérico positivo.',
			];
		}

		$source = $this->find($sourceId);
		if (!$source) {
			return [
				'abort' => true,
				'code' => 'NOT_FOUND',
				'data' => "El registro origen #$sourceId no existe en el catálogo.",
			];
		}

		if ($isBrand && $source->getIdMrk() !== 0) {
			return [
				'abort' => true,
				'code' => 'INVALID_PAYLOAD',
				'data' => "El registro #$sourceId no es una marca raíz.",
			];
		}
		if (!$isBrand && $source->getIdMrk() === 0) {
			return [
				'abort' => true,
				'code' => 'INVALID_PAYLOAD',
				'data' => "El registro #$sourceId es una marca, no un modelo.",
			];
		}

		// 1. Conteo de piezas únicas con COUNT(DISTINCT item_pub.id) y condición OR en marcas
		$childIds = [];
		if ($isBrand) {
			$dqlModels = 'SELECT m.id FROM ' . MMEntity::class . ' m WHERE m.idMrk = :sourceId';
			$childIds = array_map('intval', $this->_em->createQuery($dqlModels)
				->setParameter('sourceId', $sourceId)
				->getSingleColumnResult());

			if (!empty($childIds)) {
				$dqlCount = 'SELECT COUNT(DISTINCT i.id) FROM ' . ItemPub::class . ' i WHERE (i.mrkId = :sourceId OR i.mdlId IN (:childIds))';
				$itemCount = (int) $this->_em->createQuery($dqlCount)
					->setParameter('sourceId', $sourceId)
					->setParameter('childIds', $childIds)
					->getSingleScalarResult();
			} else {
				$dqlCount = 'SELECT COUNT(DISTINCT i.id) FROM ' . ItemPub::class . ' i WHERE i.mrkId = :sourceId';
				$itemCount = (int) $this->_em->createQuery($dqlCount)
					->setParameter('sourceId', $sourceId)
					->getSingleScalarResult();
			}
		} else {
			$dqlCount = 'SELECT COUNT(DISTINCT i.id) FROM ' . ItemPub::class . ' i WHERE i.mdlId = :sourceId';
			$itemCount = (int) $this->_em->createQuery($dqlCount)
				->setParameter('sourceId', $sourceId)
				->getSingleScalarResult();
		}

		// 2. Extraer información del borrador
		$draftBrands = $draft['brands'] ?? [];
		$draftModels = $draft['models'] ?? [];
		$draftConsolidations = $draft['consolidations'] ?? [];

		$draftDeletedBrandIds = array_map('intval', $draftBrands['deleted'] ?? []);
		$draftDeletedModelIds = array_map('intval', $draftModels['deleted'] ?? []);

		$draftBrandConsolidationMap = [];
		foreach ($draftConsolidations['brands'] ?? [] as $bc) {
			if (isset($bc['sourceId'], $bc['targetId'])) {
				$draftBrandConsolidationMap[(int)$bc['sourceId']] = (int)$bc['targetId'];
			}
		}

		$draftModelConsolidationMap = [];
		foreach ($draftConsolidations['models'] ?? [] as $mc) {
			if (isset($mc['sourceId'], $mc['targetId'])) {
				$draftModelConsolidationMap[(int)$mc['sourceId']] = (int)$mc['targetId'];
			}
		}

		$canDeleteDirectly = ($itemCount === 0);
		$canConsolidate = false;
		$transferredModels = [];
		$collisions = [];
		$code = null;
		$reason = null;

		// 3. Evaluar viabilidad de consolidación si targetId fue provisto
		if ($targetId !== null) {
			if ($targetId <= 0 || $targetId === $sourceId) {
				$canConsolidate = false;
				$code = 'INVALID_CONSOLIDATION';
				$reason = "El destino de consolidación debe ser mayor a 0 y distinto al origen (#$targetId).";
			} else {
				$isTargetDeletedInDraft = $isBrand
					? (in_array($targetId, $draftDeletedBrandIds, true) || isset($draftBrandConsolidationMap[$targetId]))
					: (in_array($targetId, $draftDeletedModelIds, true) || isset($draftModelConsolidationMap[$targetId]));

				if ($isTargetDeletedInDraft) {
					$canConsolidate = false;
					$code = 'INVALID_CONSOLIDATION';
					$reason = "El registro destino #$targetId está marcado para eliminación o consolidación en el borrador.";
				} else {
					$target = $this->find($targetId);
					if (!$target) {
						$canConsolidate = false;
						$code = 'NOT_FOUND';
						$reason = "El registro destino #$targetId no existe en el catálogo.";
					} elseif ($isBrand && $target->getIdMrk() !== 0) {
						$canConsolidate = false;
						$code = 'INVALID_CONSOLIDATION';
						$reason = "El registro destino #$targetId no es una marca válida.";
					} elseif (!$isBrand && $target->getIdMrk() === 0) {
						$canConsolidate = false;
						$code = 'INVALID_CONSOLIDATION';
						$reason = "El registro destino #$targetId no es un modelo válido.";
					} elseif (!$isBrand) {
						// Modelo: verificar pertenencia a misma marca o marca consolidada
						$sBrand = $source->getIdMrk();
						$tBrand = $target->getIdMrk();
						$isSameBrand = ($sBrand === $tBrand);
						$isConsolidatedBrand = (isset($draftBrandConsolidationMap[$sBrand]) && $draftBrandConsolidationMap[$sBrand] === $tBrand);
						if (!$isSameBrand && !$isConsolidatedBrand) {
							$canConsolidate = false;
							$code = 'INVALID_CONSOLIDATION';
							$reason = "El modelo #$sourceId no pertenece a la misma marca que #$targetId ni existe consolidación de marca activa en el borrador.";
						} elseif (!$this->_checkEquivalence($source, $target)) {
							$canConsolidate = false;
							$code = 'INVALID_CONSOLIDATION';
							$reason = "Los modelos '{$source->getName()}' (#$sourceId) y '{$target->getName()}' (#$targetId) no presentan equivalencia real de nombres ni variantes.";
						} else {
							$canConsolidate = true;
						}
					} else {
						// Marca: verificar equivalencia real
						if (!$this->_checkEquivalence($source, $target)) {
							$canConsolidate = false;
							$code = 'INVALID_CONSOLIDATION';
							$reason = "Las marcas '{$source->getName()}' (#$sourceId) y '{$target->getName()}' (#$targetId) no presentan equivalencia real de nombres ni variantes.";
						} else {
							// Evaluar modelos hijos y colisiones
							$sourceChildModels = $this->findBy(['idMrk' => $sourceId]);
							$targetChildModels = $this->findBy(['idMrk' => $targetId]);

							// Mapa de modelos existentes en la marca destino
							$targetModelMap = [];
							foreach ($targetChildModels as $tcm) {
								if (!in_array($tcm->getId(), $draftDeletedModelIds, true)) {
									$targetModelMap[mb_strtolower(trim($tcm->getName()))] = $tcm->getId();
								}
							}

							// Agregar modelos creados y actualizados en el borrador para la marca destino
							foreach ($draftModels['created'] ?? [] as $cm) {
								$cmBrand = (int)($cm['idMrk'] ?? $cm['id_mrk'] ?? 0);
								if ($cmBrand === $targetId && !empty($cm['name'])) {
									$targetModelMap[mb_strtolower(trim($cm['name']))] = (int)($cm['id'] ?? 0);
								}
							}
							foreach ($draftModels['updated'] ?? [] as $um) {
								$umBrand = (int)($um['idMrk'] ?? $um['id_mrk'] ?? 0);
								if ($umBrand === $targetId && !empty($um['name'])) {
									$targetModelMap[mb_strtolower(trim($um['name']))] = (int)($um['id'] ?? 0);
								}
							}

							$hasPendingCollision = false;
							foreach ($sourceChildModels as $scm) {
								$scmId = $scm->getId();
								if (in_array($scmId, $draftDeletedModelIds, true)) {
									continue;
								}
								if (isset($draftModelConsolidationMap[$scmId])) {
									continue; // Colisión resuelta en borrador
								}

								$scmNorm = mb_strtolower(trim($scm->getName()));
								if (isset($targetModelMap[$scmNorm])) {
									$collisions[] = [
										'sourceModelId' => $scmId,
										'name' => $scm->getName(),
										'targetModelId' => $targetModelMap[$scmNorm],
									];
									$hasPendingCollision = true;
								} else {
									$transferredModels[] = [
										'id' => $scmId,
										'name' => $scm->getName(),
									];
								}
							}

							if ($hasPendingCollision) {
								$canConsolidate = false;
								$code = 'COLLISION_CONFLICT';
								$reason = "Existen " . count($collisions) . " modelo(s) colisionado(s) que requieren consolidación explícita en la marca destino.";
							} else {
								$canConsolidate = true;
							}
						}
					}
				}
			}
		} else {
			if (!$canDeleteDirectly) {
				$code = 'DEPENDENCY_CONFLICT';
				$reason = "Existen $itemCount publicación(es) de inventario vinculadas al registro. Se requiere consolidar hacia un registro canónico para preservar las piezas.";
			}
		}

		return [
			'abort' => false,
			'data' => [
				'sourceId' => $sourceId,
				'isBrand' => $isBrand,
				'targetId' => $targetId,
				'itemCount' => $itemCount,
				'canDeleteDirectly' => $canDeleteDirectly,
				'canConsolidate' => $canConsolidate,
				'transferredModels' => $transferredModels,
				'collisions' => $collisions,
				'code' => $canConsolidate ? null : $code,
				'reason' => $canConsolidate ? null : $reason,
			],
		];
	}

}
