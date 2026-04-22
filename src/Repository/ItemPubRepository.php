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
	public function getPubsBySlug(string $slug, string $waId): \Doctrine\ORM\Query
	{
		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '.
		'WHERE it.slug = :slug OR it.waId = :waId AND it.isActive = 1 '.
		'ORDER BY it.created DESC, it.id DESC';

		return $this->_em->createQuery($dql)
			->setParameter('slug', $slug)
			->setParameter('waId', $waId);
	}

	/** */
	public function getAllMsgAfterUpdate(string $slug, int $lastUpdate): array
	{
		$safeLastUpdate = $lastUpdate;
		$updatedAt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $safeLastUpdate / 1000));
		if ($updatedAt === false) {
			$updatedAt = new \DateTimeImmutable('@' . floor($safeLastUpdate / 1000));
		}

		$tz = new \DateTimeZone(date_default_timezone_get()); 
		$updatedAt = $updatedAt->setTimezone($tz);

		$dql = 'SELECT it FROM ' . ItemPub::class . ' it '
			. 'WHERE it.slug = :slug '
			. 'AND it.updatedAt >= :updatedAt '
			. 'ORDER BY it.updatedAt DESC, it.id DESC';

		$items = $this->_em->createQuery($dql)
			->setParameter('slug', $slug)
			->setParameter('updatedAt', $updatedAt)
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

			// Separar items inactivos o con status 501
			if ((!isset($item['isActive']) || $item['isActive'] == 0) || (isset($item['stt']) && $item['stt'] == 501)) {
				$inactives[] = [
					'id' => $item['id'],
					'idSrc' => $item['idSrc'] ?? null,
					'iku' => $item['iku'] ?? null,
					'stt' => $item['stt'] ?? null,
					'isActive' => $item['isActive'] ?? 0,
				];
			} else {
				// Items activos
				if ($index < 5) {
					$results[] = $item;
				} else {
					$pendings[] = $item['id'];
				}
			}
		}

		return [
			'results' => $results,
			'pendings' => $pendings,
			'inactives' => $inactives,
			'last' => $last,
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
			SELECT it
			FROM " . ItemPub::class . " it
			WHERE it.mrkId = :mrkId
				AND it.mdlId = :mdlId
				AND it.isActive = :isActive
				AND it.anioInicio <= :anioFin
				AND it.anioFin >= :anioInicio
			";

		if (!empty($waId)) {
			$params['waId'] = $waId;
			if (!empty($inTo)) {
				$dql .= " AND it.waId = :waId";
			} else {
				$dql .= " AND it.waId <> :waId";
			}
		}

		if (!empty($slug)) {
			$params['slug'] = $slug;
			if (!empty($inTo)) {
				$dql .= " OR it.slug = :slug";
			} else {
				$dql .= " AND it.slug <> :slug";
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
		$result = [];
		$lado = '';
		$poss = '';

		$obj = null;
    if($data['id'] ?? 0 != 0) {
			$obj = $this->getIfExistPubById($data['id']);
		}

		if($obj == null) {
			$obj = new ItemPub();
			$obj = $obj->fromJson($data);
		}else{
			$action = 'edt';
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
			return ['abort' => false, 'action' => $action, 'body' => ['id' => $id]];
		} catch (\Throwable $th) {
			return ['abort' => true, 'action' => 'error', 'body' => $th->getMessage()];
		}
		return ['abort' => false, "action" => $action, "body" => $result];
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

}
