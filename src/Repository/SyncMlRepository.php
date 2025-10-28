<?php

namespace App\Repository;

use App\Entity\SyncMl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncMl>
 *
 * @method SyncMl|null find($id, $lockMode = null, $lockVersion = null)
 * @method SyncMl|null findOneBy(array $criteria, array $orderBy = null)
 * @method SyncMl[]    findAll()
 * @method SyncMl[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SyncMlRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, SyncMl::class);
	}

	/**
	* Recuperamos el mensaje solicitado por el id del mensaje que crea ML
	*/
	public function getMsgByMsgId(String $msgId): \Doctrine\ORM\Query
	{
		$dql = 'SELECT n FROM ' . SyncMl::class . ' n '.
		'WHERE n.msg_id = :idMsg';
		return $this->_em->createQuery($dql)->setParameter('idMsg', $msgId);
	}

	/**
	* Recuperamos todos los mensajes que han llegado a partir
	* del id del mensaje enviado por parametro
	*/
	public function getAllMsgAfterByMsgId(?String $msgId = ''): array
	{
		$order = 'ORDER BY n.receivedAt DESC';
		$dql = 'SELECT n FROM ' . SyncMl::class . ' n ';
		if($msgId == '' || $msgId == null) {
			$dql = $dql.$order;
			return $this->_em->createQuery($dql)->getArrayResult();
		}

		$dqlLast = $this->getMsgByMsgId($msgId);
		$has = $dqlLast->execute();
		if($has) {
			$dql = $dql.'WHERE n.sendAt > :date ' .$order;
			return $this->_em->createQuery($dql)
				->setParameter('date', $has[0]->getSendAt())
				->getArrayResult();
		}

		return [];
	}

	/**
	* Guardamos un nuevo mensaje de notificacion
	*/
	public function set(array $msg): void
	{
		$dto = new SyncMl();
		$dto = $dto->set($msg);
		$dql = $this->getMsgByMsgId($dto->getMsgId());
		$has = $dql->execute();
		if($has) {
			return;
		}
		$this->_em->persist($dto);
		try {
			$this->_em->flush();
		} catch (\Throwable $th) {
			//throw $th;
		}
	}

}
