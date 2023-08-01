<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\AO1Marcas;

/**
 * @method AO1Marcas|null find($id, $lockMode = null, $lockVersion = null)
 * @method AO1Marcas|null findOneBy(array $criteria, array $orderBy = null)
 * @method AO1Marcas[]    findAll()
 * @method AO1Marcas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AO1MarcasRepository extends ServiceEntityRepository
{

	private $result = ['abort' => false, 'msg' => 'ok', 'body' => []];

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, AO1Marcas::class);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getAllAsArray(): array
	{
		return $this->_em->createQuery(
			'SELECT mk FROM ' . AO1Marcas::class . ' mk '
		)->getScalarResult();
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getAllNameAsArray(): array
	{
		$dql = 'SELECT mrk.{id, nombre} FROM ' . AO1Marcas::class . ' mrk '.
		'ORDER BY mrk.nombre ASC';

		return $this->_em->createQuery($dql)->getScalarResult();
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function add(AO1Marcas $entity, bool $flush = true): void
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function remove(AO1Marcas $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getAllAutos(): \Doctrine\ORM\Query
	{
		$dql = 'SELECT mrk, partial md.{id, nombre, simyls} FROM ' . AO1Marcas::class . ' mrk '.
		'JOIN mrk.modelos md '.
		'ORDER BY mrk.nombre ASC';

		return $this->_em->createQuery($dql);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getAllMarcas(): \Doctrine\ORM\Query
	{
		$dql = 'SELECT mrk FROM ' . AO1Marcas::class . ' mrk '.
		'ORDER BY mrk.nombre ASC';

		return $this->_em->createQuery($dql);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getMarcaById(int $id): \Doctrine\ORM\Query
	{
		$dql = 'SELECT mrk FROM ' . AO1Marcas::class . ' mrk '.
		'WHERE mrk.id = :id';

		return $this->_em->createQuery($dql)->setParameter('id', $id);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function setMarca(array $marca)
	{

		$mrk = new AO1Marcas();
		if($marca['id'] != 0) {
			$dql = $this->getMarcaById($marca['id']);
			$obj = $dql->execute();
			if($obj) {
				$mrk = $obj[0];
			}
		}

		$mrk->setNombre($marca['marca']);
		$mrk->setLogo($marca['logo']);
		$mrk->setGrupo($marca['grupo']);
		if(array_key_exists('simyls', $marca)) {
			$mrk->setSimyls($marca['simyls']);
		}else{
			$mrk->setSimyls(['radec' => '0', 'aldo' => '0']);
		}

		$this->_em->persist($mrk);
		try {
			$this->_em->flush();
			$this->result['body'] = $mrk->getId();
		} catch (\Throwable $th) {
			$this->result['abort'] = true;
			$this->result['msg'] = $th->getMessage();
			$this->result['body'] = 'No se guardo la marca';
		}

		return $this->result;
	}

}
