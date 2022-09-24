<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;

/**
 * @method AO2Modelos|null find($id, $lockMode = null, $lockVersion = null)
 * @method AO2Modelos|null findOneBy(array $criteria, array $orderBy = null)
 * @method AO2Modelos[]    findAll()
 * @method AO2Modelos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AO2ModelosRepository extends ServiceEntityRepository
{

	private $result = ['abort' => false, 'msg' => 'ok', 'body' => []];

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, AO2Modelos::class);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function add(AO2Modelos $entity, bool $flush = true): void
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
	public function remove(AO2Modelos $entity, bool $flush = true): void
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
	public function getAllModelosByIdMarca(int $idMarca): \Doctrine\ORM\Query
	{
		$dql = 'SELECT md, partial mrk.{id} FROM ' . AO2Modelos::class . ' md '.
		'JOIN md.marca mrk '.
		'WHERE md.marca = :idMarca '.
		'ORDER BY md.nombre ASC';

		return $this->_em->createQuery($dql)->setParameter('idMarca', $idMarca);
	}
	

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function getModeloById(int $id): \Doctrine\ORM\Query
	{
		$dql = 'SELECT md FROM ' . AO2Modelos::class . ' md '.
		'WHERE md.id = :id ';

		return $this->_em->createQuery($dql)->setParameter('id', $id);
	}
	
	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function setModelo(array $modelo)
	{
		$mdl = new AO2Modelos();
		if($modelo['id'] != 0) {
			$dql = $this->getModeloById($modelo['id']);
			$obj = $dql->execute();
			if($obj) {
				$mdl = $obj[0];
			}
		}else{
			$mdl->setMarca($this->_em->getPartialReference(AO1Marcas::class, $modelo['mrkId']));
		}

		$mdl->setNombre($modelo['modelo']);
		if(array_key_exists('simyls', $modelo)) {
			$mdl->setSimyls($modelo['simyls']);
		}else{
			$mdl->setSimyls(['radec' => '0', 'aldo' => '0']);
		}

		$this->_em->persist($mdl);
		try {
			$this->_em->flush();
			$this->result['body'] = $mdl->getId();
		} catch (\Throwable $th) {
			$this->result['abort'] = true;
			$this->result['msg'] = $th->getMessage();
			$this->result['body'] = 'No se guardo el Modelo';
		}

		return $this->result;
	}
}
