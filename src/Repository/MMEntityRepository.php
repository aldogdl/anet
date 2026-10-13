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

		$createdBrands = $brandsPayload['created'] ?? [];
		$updatedBrands = $brandsPayload['updated'] ?? [];
		$deletedBrandIds = array_values(array_unique(array_filter(array_map('intval', $brandsPayload['deleted'] ?? []))));

		$createdModels = $modelsPayload['created'] ?? [];
		$updatedModels = $modelsPayload['updated'] ?? [];
		$deletedModelIds = array_values(array_unique(array_filter(array_map('intval', $modelsPayload['deleted'] ?? []))));

		// 1. Validaciones previas de nombres de marcas
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

		// 2. Validaciones previas de nombres de modelos (únicos por marca)
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

		// 3. Verificación de dependencias de inventario antes de eliminar
		if (!empty($deletedModelIds)) {
			$dql = 'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i WHERE i.mdlId IN (:deletedModelIds)';
			$itemCount = (int) $this->_em->createQuery($dql)
				->setParameter('deletedModelIds', $deletedModelIds)
				->getSingleScalarResult();
			if ($itemCount > 0) {
				return [
					'abort' => true,
					'code' => 'DEPENDENCY_CONFLICT',
					'data' => "No se pueden eliminar los modelos seleccionados: existen $itemCount publicación(es) de inventario asociadas.",
				];
			}
		}

		if (!empty($deletedBrandIds)) {
			// Directo en marcas
			$dql = 'SELECT COUNT(i.id) FROM ' . ItemPub::class . ' i WHERE i.mrkId IN (:deletedBrandIds)';
			$brandItemCount = (int) $this->_em->createQuery($dql)
				->setParameter('deletedBrandIds', $deletedBrandIds)
				->getSingleScalarResult();
			if ($brandItemCount > 0) {
				return [
					'abort' => true,
					'code' => 'DEPENDENCY_CONFLICT',
					'data' => "No se pueden eliminar las marcas seleccionadas: existen $brandItemCount publicación(es) de inventario asociadas.",
				];
			}

			// Modelos hijos de las marcas a eliminar
			$dql = 'SELECT m.id FROM ' . MMEntity::class . ' m WHERE m.idMrk IN (:deletedBrandIds)';
			$modelsOfBrands = $this->_em->createQuery($dql)
				->setParameter('deletedBrandIds', $deletedBrandIds)
				->getSingleColumnResult();
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

		// 4. Verificación de existencia para ediciones
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

		// 5. Ejecución del lote transaccional
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
				&$brandIdMap,
				&$modelIdMap
			) {
				// A. Eliminar modelos
				foreach ($deletedModelIds as $mid) {
					$entity = $this->find($mid);
					if ($entity) {
						$this->_em->remove($entity);
					}
				}

				// B. Eliminar marcas y sus modelos en cascada
				foreach ($deletedBrandIds as $bid) {
					$brand = $this->find($bid);
					if ($brand) {
						$childModels = $this->findBy(['idMrk' => $bid]);
						foreach ($childModels as $child) {
							$this->_em->remove($child);
						}
						$this->_em->remove($brand);
					}
				}

				// C. Actualizar marcas existentes
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

				// D. Crear marcas nuevas y registrar mapeo de tempId
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
					// Flush para que MySQL asigne el ID autoincremental
					$this->_em->flush();
					$brandIdMap[(string)$tempId] = $brand->getId();
				}

				// E. Actualizar modelos existentes
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

				// F. Crear modelos nuevos y registrar mapeo de tempId
				foreach ($createdModels as $cm) {
					$tempId = (int)$cm['id'];
					$rawMrkId = isset($cm['idMrk']) ? (int)$cm['idMrk'] : (isset($cm['id_mrk']) ? (int)$cm['id_mrk'] : 0);
					if ($rawMrkId < 0 && isset($brandIdMap[(string)$rawMrkId])) {
						$rawMrkId = $brandIdMap[(string)$rawMrkId];
					}
					if ($rawMrkId <= 0) {
						throw new \InvalidArgumentException("El modelo '{$cm['name']}' no tiene una marca padre válida (idMrk: $rawMrkId).");
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

}
