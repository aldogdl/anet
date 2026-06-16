<?php

namespace App\Repository;

use App\Entity\SysCom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SysCom>
 *
 * @method SysCom|null find($id, $lockMode = null, $lockVersion = null)
 * @method SysCom|null findOneBy(array $criteria, array $orderBy = null)
 * @method SysCom[]    findAll()
 * @method SysCom[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SysComRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
			parent::__construct($registry, SysCom::class);
	}

	/**
	 * Undocumented function
	 *
	 * @param SysCom $entity
	 * @param boolean $flush
	 * @return SysCom
	 */
	public function add(SysCom $entity, bool $flush = true): SysCom
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
		}
		return $entity;
	}

	/** */
	public function fetchUser(array $data): ?SysCom
	{
		$sql = 'SELECT sc FROM '. SysCom::class .' sc '.
				'WHERE sc.waId = :waId AND sc.slug = :slug AND sc.device = :device';
		$res = $this->_em->createQuery($sql)->setParameters([
			'waId' => $data['waId'], 'slug' => $data['slug'], 'device' => $data['device']
		])->setMaxResults(1)->getOneOrNullResult();
		return $res;
	}

	/** */
	public function checkIsOk(string $target, string $waId, string $slug): string
	{
		$sql = 'SELECT sc FROM '. SysCom::class .' sc '.
			'WHERE sc.waId = :waId AND sc.slug = :slug';

		$res = $this->_em->createQuery($sql)->setParameters([
			'waId' => $waId, 'slug' => $slug
		])->setMaxResults(1)->getOneOrNullResult();

		if($res) {
			if($res->getFbTok() == $target) {
				return $target;
			}
		}

		return '';
	}

	/** */
	public function getTokensBySlug(string $slug, string $waIdExcepto = '0'): array
	{
		$sql = 'SELECT sc.fbtok FROM '. SysCom::class .' sc '.
				'WHERE sc.slug = :slug';
		$params = ['slug' => $slug];

		if($waIdExcepto != '0') {
			$sql .= ' AND sc.waId != :waIdExcepto';
			$params['waIdExcepto'] = $waIdExcepto;
		}

		$res = $this->_em->createQuery($sql)->setParameters($params)->getArrayResult();
		return array_column($res, 'fbtok');
	}

	/** */
	public function updateDataCom(array $data): array
	{
		$exist = $this->fetchUser($data);
		if($exist) {
			$exist->setLastUpdate(new \DateTimeImmutable('now'));
			$exist->setFbtok($data['fbtok']);
			$exist->setTaId($data['taId']);
			$user = $exist;
		}else{
			$user = new SysCom();
			$user = $user->fromJson($data);
		}
		$this->add($user);
		return $user->toJson();
	}

}
