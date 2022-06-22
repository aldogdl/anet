<?php

namespace App\Repository;

use App\Entity\Ordenes;
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
	public function add(OrdenResps $entity, bool $flush = true): void
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
	public function remove(OrdenResps $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
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
	private function getOrden(int $id): array
	{
		$dql = $this->_em->getRepository(Ordenes::class)->getDataOrdenById($id, false, true);
		$orden = $dql->getArrayResult();
		return ($orden) ? $orden[0] : [];
	}
}
