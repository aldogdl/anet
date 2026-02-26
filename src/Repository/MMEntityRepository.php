<?php

namespace App\Repository;

use App\Entity\MMEntity;
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
	 * Recuperamos todos los elementos de manera slim
	*/
	public function getMMSlim(String $tipo) : array
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

		if($tipo == 'mrks') {
			return ['abort' => false, 'body' => $mrks];
		}

		// 2da pasada: llenar nm
		foreach ($mdls as &$m) {
			$m['nm'] = $brandNameById[$m['im']] ?? '';
		}
		unset($m);
				
		if($tipo == 'mdls') {
			return ['abort' => false, 'body' => $mdls];
		}

		return ['abort' => false, 'data' => ['mrks' => $mrks, 'mdls' => $mdls]];
	}

	/** 
	 * Recuperamos todos los elementos en caso de que $idMrk == null
	 * se refiere a las marcas en caso contrario son modelos
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
		$obj = new MMEntity();
		$obj = $obj->fromJson($mm);
		if($obj->getId() == 0) {
			// Ya que se pretende agregar, buscamos en ls BD
			// si el nombre ya existe para evitar duplicados
			if($this->existByName($obj->getName())) {
				return ['abort' => true, 'data' => 'Ya existe un elemento con ese nombre'];
			}
		}

		try {
			$this->_em->persist($obj);
			$this->_em->flush();
			return ['abort' => false, 'data' => $this->getMMSlim('alls')];
		} catch (\Throwable $th) {
			return $this->json(['abort' => true, 'data' => $th->getMessage()]);
		}

		return $this->json(['abort' => true, 'data' => 'Error inesperado']);

	}

}
