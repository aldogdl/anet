<?php

namespace App\Repository;

use App\Entity\ScmReceivers;
use App\Entity\ScmCamp;
use App\Entity\NG2Contactos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScmReceivers|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScmReceivers|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScmReceivers[]    findAll()
 * @method ScmReceivers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScmReceiversRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ScmReceivers::class);
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function add(ScmReceivers $entity, bool $flush = true): void
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
	public function remove(ScmReceivers $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	/**
	 * Construimos el registro para crear un id unico, el cual sera guardado en
	 * el servidor remoto por cada proveedor como una key foranea, solo para
	 * identificar el registro local creado por el SCM y el remoto echo por el receiver.
	*/
	public function setRegMsgSended(array $data): int
	{
		$obj = new ScmReceivers();
		$obj->setCamp($this->_em->getPartialReference(ScmCamp::class, $data['camp']));
		$obj->setReceiver($this->_em->getPartialReference(NG2Contactos::class, $data['receiver']));
		$obj->setStt($data['stt']);
		$this->add($obj, true);
		return $obj->getId();
	}

	/** */
	public function spellParams(String $params): array
	{
		$regs = explode(',', $params);
		$rota = count($regs);
		$seeFin = [];
		// Ej. 14-6-2-nth
		for ($i=0; $i < $rota; $i++) {
			$see = [];
			$partes = explode('-', $regs[$i]);
			$see['ord'] = $partes[0];
			$see['cot'] = $partes[1];
			$see['avo'] = $partes[2];
			try {
				$see['pre'] = $partes[3];
			} catch (\Throwable $th) {}
			$seeFin[$see['avo']][] = $see;
		}

		// Buscamos la campaña adecuada
		foreach ($seeFin as $avo => $reg) {
			$seeFin[$avo] = $this->fetchIdCampainByIdOrden($avo, $reg);
		}
		return $seeFin;
	}
	
	/** */
	public function getIdsRegsLast(int $cant): array
	{
		$results = [];
		$dql = 'SELECT partial cr.{id, sendAt, readAt}, partial c.{id, target}, partial cot.{id}, partial avo.{id} '.
		'FROM ' . ScmReceivers::class . ' cr '.
		'JOIN cr.receiver cot '.
		'JOIN cr.camp c '.
		'JOIN c.remiter avo '.
		'ORDER BY cr.id DESC';

		$results = $this->_em->createQuery($dql)->setMaxResults($cant)
			->getScalarResult();

		return $results;
	}
	
	/** */
	public function fetchIdCampainByIdOrden(String $idAvo, array $reg): array
	{
		$dql = 'SELECT c FROM ' . ScmCamp::class . ' c '.
		'WHERE c.remiter = :avo';
		$results = $this->_em->createQuery($dql)->setParameter('avo', $idAvo)->getArrayResult();
		$usadas = [];
		$forSave = [];
		$rota = count($results);
		$vueltas = count($reg);
		for ($r=0; $r < $vueltas; $r++) { 
			for ($i=0; $i < $rota; $i++) { 
				if($results[$i]['src']['id'] == (integer) $reg[$r]['ord']) {
					if(!in_array($results[$i]['id'], $usadas)) {
						$reg[$r]['cam'] = $results[$i]['id'];
						$reg[$r]['send'] = $results[$i]['createdAt'];
						$forSave[] = $reg[$r];
						$usadas[] = $results[$i]['id'];
					}
					continue;
				}
			}
		}

		return $forSave;
	}

  /**
  * Recuperamos las campañas indicadas por parametro
  */
  public function getRegsPushSeeByids(array $ids): \Doctrine\ORM\Query
  {
    $dql = 'SELECT partial rcm.{id}, partial cam.{id, target, src}, partial avo.{id, nombre}, partial rcv.{id} '.
		'FROM ' . ScmReceivers::class . ' rcm '.
    'JOIN rcm.camp cam '.
    'JOIN rcm.receiver rcv '.
    'JOIN cam.remiter avo '.
    'WHERE rcm.id IN (:ids) '.
    'ORDER BY avo.id ASC';

    return $this->_em->createQuery($dql)->setParameter('ids', $ids);
  }

	/**
  * Recuperamos los registros de los receptores por el id de la campaña
  */
  public function getRegsReceiversByIdCamp(int $id): \Doctrine\ORM\Query
  {
    $dql = 'SELECT r, partial c.{id} FROM ' . ScmReceivers::class . ' r '.
	'JOIN r.receiver c '.
	'WHERE r.camp = :idCamp '.
	'ORDER BY r.receiver ASC';

    return $this->_em->createQuery($dql)->setParameter('idCamp', $id);
  }
}
