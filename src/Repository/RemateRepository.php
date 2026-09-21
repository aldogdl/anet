<?php

namespace App\Repository;

use App\Entity\Remate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Remate>
 *
 * @method Remate|null find($id, $lockMode = null, $lockVersion = null)
 * @method Remate|null findOneBy(array $criteria, array $orderBy = null)
 * @method Remate[]    findAll()
 * @method Remate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remate::class);
    }

    public function save(Remate $entity, bool $flush = false): void
    {
        $this->_em->persist($entity);

        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Remate $entity, bool $flush = false): void
    {
        $this->_em->remove($entity);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Busca un remate por su IKU y slug del dueño usando DQL
     */
    public function findOneByIkuAndOwner(string $iku, string $ownerSlug): ?Remate
    {
        $dql = 'SELECT r FROM ' . Remate::class . ' r ' .
               'WHERE r.iku = :iku AND r.ownerSlug = :ownerSlug';

        return $this->_em->createQuery($dql)
            ->setParameters([
                'iku' => $iku,
                'ownerSlug' => $ownerSlug,
            ])
            ->getOneOrNullResult();
    }

    /**
     * Busca un remate por su remateId público o ID numérico usando DQL
     */
    public function findByRemateIdOrId(string $remateId): ?Remate
    {
        $params = ['remateId' => $remateId];
        $where = 'r.remateId = :remateId';

        if (is_numeric($remateId)) {
            $where .= ' OR r.id = :numId';
            $params['numId'] = (int) $remateId;
        }

        $dql = 'SELECT r FROM ' . Remate::class . ' r WHERE ' . $where;

        return $this->_em->createQuery($dql)
            ->setParameters($params)
            ->getOneOrNullResult();
    }

    /**
     * Lista remates propios de un negocio / colaborador con paginado usando DQL
     *
     * @return Remate[]
     */
    public function findByOwner(string $ownerSlug, ?string $ownerWaId = null, int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = ['ownerSlug' => $ownerSlug];

        $dql = 'SELECT r FROM ' . Remate::class . ' r WHERE r.ownerSlug = :ownerSlug AND r.status != 501';

        if ($ownerWaId !== null && $ownerWaId !== '' && $ownerWaId !== '0') {
            $dql .= ' AND r.ownerWaId = :ownerWaId';
            $params['ownerWaId'] = $ownerWaId;
        }

        $dql .= ' ORDER BY r.createdAt DESC, r.id DESC';

        return $this->_em->createQuery($dql)
            ->setParameters($params)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getResult();
    }

    /**
     * Cuenta total de remates de un dueño usando DQL
     */
    public function countByOwner(string $ownerSlug, ?string $ownerWaId = null): int
    {
        $params = ['ownerSlug' => $ownerSlug];
        $dql = 'SELECT COUNT(r.id) FROM ' . Remate::class . ' r WHERE r.ownerSlug = :ownerSlug AND r.status != 501';

        if ($ownerWaId !== null && $ownerWaId !== '' && $ownerWaId !== '0') {
            $dql .= ' AND r.ownerWaId = :ownerWaId';
            $params['ownerWaId'] = $ownerWaId;
        }

        return (int) $this->_em->createQuery($dql)
            ->setParameters($params)
            ->getSingleScalarResult();
    }

    /**
     * Lista remates activos comunitarios usando DQL
     *
     * @return Remate[]
     */
    public function findActiveCommunity(?int $mrkId = null, ?int $mdlId = null, int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $dql = 'SELECT r FROM ' . Remate::class . ' r WHERE r.status = 0';

        if ($mrkId !== null && $mrkId > 0) {
            $dql .= ' AND r.mrkId = :mrkId';
            $params['mrkId'] = $mrkId;
        }

        if ($mdlId !== null && $mdlId > 0) {
            $dql .= ' AND r.mdlId = :mdlId';
            $params['mdlId'] = $mdlId;
        }

        $dql .= ' ORDER BY r.createdAt DESC, r.id DESC';

        $query = $this->_em->createQuery($dql);
        if (!empty($params)) {
            $query->setParameters($params);
        }

        return $query->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getResult();
    }
}
