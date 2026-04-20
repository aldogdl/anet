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
	public function getMsgByMsgId(string $msgId): ?SyncMl
	{
		return $this->createQueryBuilder('n')
			->where('n.msg_id = :idMsg')
			->setParameter('idMsg', $msgId)
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	* Necesitamos recuperar el mensaje por el id del producto.
	*/
	public function getMsgByIdProduct(string $id): ?SyncMl
	{
		$dql = 'SELECT n FROM ' . SyncMl::class . ' n '
			. 'WHERE n.resource LIKE :id '
			. 'ORDER BY n.receivedAt ASC';

		return $this->_em->createQuery($dql)
			->setParameter('id', '%' . $id . '%')
			->setMaxResults(1)
			->getOneOrNullResult();
	}

	/**
	* Recuperamos todos los mensajes que han llegado a partir
	* del id del mensaje enviado por parametro
	*/
	public function getAllMsgAfterByMsgId(int|string $idUser, ?string $msgId = null): array
	{
		$qb = $this->createQueryBuilder('n')
			->where('n.user_id = :idUser')
			->setParameter('idUser', $idUser)
			->orderBy('n.receivedAt', 'DESC');

		if ($msgId !== null && $msgId !== '') {
			$idType = mb_strpos($msgId, 'ML');
			if($idType !== false && $idType < 3) {
				$lastMsg = $this->getMsgByIdProduct($msgId);
			} else {
				$lastMsg = $this->getMsgByMsgId($msgId);
			}
			if (!$lastMsg || !$lastMsg->getSendAt()) {
				return [];
			}

			$qb->andWhere('n.receivedAt > :sendAt')
				->setParameter('sendAt', $lastMsg->getSendAt());
		}

    $this->deleteOlderThan48Hours($idUser);
		return $qb->getQuery()->getArrayResult();
	}

	/**
	* Guardamos un nuevo mensaje de notificacion
	*/
	public function set(array $msg): void
	{
		$dto = new SyncMl();
		$dto = $dto->set($msg);
		$existing = $this->getMsgByMsgId($dto->getMsgId());
		if ($existing) {
			return;
		}
		$this->_em->persist($dto);
		try {
			$this->_em->flush();
		} catch (\Throwable $th) {
			// Se ignora el error, pero es recomendable loggear si sucede.
		}
	}

	/**
	* Elimina registros con receivedAt mayores a 48 horas para un usuario específico
	*/
	public function deleteOlderThan48Hours(int|string $userId): int
	{
		$threshold = new \DateTimeImmutable('-48 hours');
		return $this->createQueryBuilder('n')
			->delete()
			->where('n.receivedAt < :threshold')
			->andWhere('n.user_id = :userId')
			->setParameter('threshold', $threshold)
			->setParameter('userId', $userId)
			->getQuery()
			->execute();
	}

}
