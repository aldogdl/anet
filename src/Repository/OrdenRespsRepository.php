<?php

namespace App\Repository;

use App\Entity\NG2Contactos;
use App\Entity\Ordenes;
use App\Entity\OrdenPiezas;
use App\Entity\OrdenResps;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OrdenResps|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdenResps|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdenResps[]    findAll()
 * @method OrdenResps[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdenRespsRepository extends ServiceEntityRepository
{

	private $result = ['abort' => false, 'msg' => 'ok', 'body' => []];

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, OrdenResps::class);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function add(OrdenResps $entity, bool $flush = true, bool $returnObj = false)
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
			if($returnObj) {
				return $entity;
			}
			return $entity->getId();
		}
		return 0;
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function remove(OrdenResps $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	/**
	 * Guardamos la respuesta del cotizador
	*/
	public function setRespuesta(array $data, bool $isLocal = false): array
	{
		$obj = new OrdenResps();
		$obj->setOrden($this->_em->getPartialReference(Ordenes::class, $data['idOrden']));
		$obj->setPieza($this->_em->getPartialReference(OrdenPiezas::class, $data['idPieza']));
		$obj->setOwn($this->_em->getPartialReference(NG2Contactos::class, $data['own']));
		$obj->setCosto($data['costo']);
		$obj->setObservs($data['deta']);
		$obj->setFotos($data['fotos']);

		try {
			$obj = $this->add($obj, true, 'obj');
			$this->result['body'] = $obj->getId();
			if($isLocal) {
				$this->revisarIdTable($obj, $data['id']);
			}
		} catch (\Throwable $th) {
			$this->result = ['abort' => true, 'msg' => $th->getMessage(), 'body' => 'Error al Guardar la respuesta'];
		}

		return $this->result;
	}

	/** */
	public function getRespuestaCentinela(int $idOrd): \Doctrine\ORM\Query
	{	
		$dql = 'SELECT partial r.{id, costo}, partial o.{id}, partial p.{id}, partial c.{id}, partial a.{id} FROM ' .
		OrdenResps::class . ' r '.
		'JOIN r.orden o '.
		'JOIN r.pieza p '.
		'JOIN r.own c '.
		'LEFT JOIN o.avo a '.
		'WHERE r.orden = :id';

		return $this->_em->createQuery($dql)->setParameter('id', $idOrd);
	}
	
	/** */
	public function getRespuestaByIds(array $ids): \Doctrine\ORM\Query
	{	
		$dql = 'SELECT r, partial o.{id}, partial p.{id}, partial c.{id}, partial a.{id} FROM ' .
		OrdenResps::class . ' r '.
		'JOIN r.orden o '.
		'JOIN r.pieza p '.
		'JOIN r.own c '.
		'LEFT JOIN o.avo a '.
		'WHERE r.id IN (:ids)';

		return $this->_em->createQuery($dql)->setParameter('ids', $ids);
	}
	
	/** */
	public function getRespsByIdPzas(array $ids): \Doctrine\ORM\Query
	{	
		$dql = 'SELECT r, partial o.{id}, partial p.{id}, partial c.{id}, partial a.{id} FROM ' .
		OrdenResps::class . ' r '.
		'JOIN r.orden o '.
		'JOIN r.pieza p '.
		'JOIN r.own c '.
		'LEFT JOIN o.avo a '.
		'WHERE r.pieza IN (:ids)';

		return $this->_em->createQuery($dql)->setParameter('ids', $ids);
	}

	/** */
	public function getTargetById(String $target, array $src): array 
	{
		$msgErr = '0';
		switch ($target) {
			case 'orden':
				if(array_key_exists('id', $src)) {
					$this->result['body'] = $this->getOrden($src['id']);
				}else{
					$msgErr = 'No se envió el ID de la '.$target;
				}
				break;
			case 'pieza':
				# code...
				break;
			case 'inventario':
				# code...
				break;
			
			default:
				# Generales
				$this->result['body'] = [];
				break;
		}

		if($msgErr != '0') {
			$this->result['abort'] = true;
			$this->result['msg'] = 'Error';
			$this->result['body'] = $msgErr;
		}
		return $this->result;
	}

	/**
	 * Obtenemos la orden sin dueño pero con sus piezas
	*/
	private function getOrden(int $id, string $hyd = 'array'): array | Ordenes
	{
		$dql = $this->_em->getRepository(Ordenes::class)->getDataOrdenById($id, false, true);
		if($hyd == 'array') {
			$orden = $dql->getArrayResult();
		}else{
			$orden = $dql->execute();
		}
		return ($orden) ? $orden[0] : [];
	}

	///
	private function revisarIdTable(OrdenResps $ord, int $id)
	{
		if($ord->getId() != $id) {
			$dql = 'UPDATE ' . OrdenResps::class . ' e '.
			'SET e.id = :idN '.
			'WHERE e.id = :id';
			try {
				$this->_em->createQuery($dql)->setParameters([
					'idN' => $id, 'id' => $ord->getId()
				])->execute();

			} catch (\Throwable $th) {
				$this->result['abort'] = true;
				$this->result['msg'] = $th->getMessage();
				$this->borrarAndCompactar($ord);
			} 
		}
	}

	/** */
	public function borrarAndCompactar(OrdenResps $obj)
	{
		$this->_em->remove($obj);
		
		$dql = 'SELECT COUNT(o.id) FROM ' . OrdenResps::class . ' o ';
		$res = $this->_em->createQuery($dql)->getSingleScalarResult();
		if($res > 0) {
			$connDb = $this->getEntityManager()->getConnection();
			$connDb->prepare('ALTER TABLE '.OrdenResps::class.' AUTO_INCREMENT = '.$res.';')->executeStatement();
		}
	}
}
