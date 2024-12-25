<?php

namespace App\Repository;

use App\Entity\Fcm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fcm>
 *
 * @method Fcm|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fcm|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fcm[]    findAll()
 * @method Fcm[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FcmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fcm::class);
    }

    /**
     * Este método recupera la entidad Fcm basada en el ID de WhatsApp (waId) proporcionado.
     * 
     * @param string $waId El ID de WhatsApp para buscar.
     * @return Fcm|null La entidad Fcm si se encuentra, o null si no se encuentra.
     */
    public function getTokenByWaId($waId): ?Fcm
    {
        $dql = 'SELECT f FROM App\Entity\Fcm f WHERE f.waId = :waId';
        return $this->_em->createQuery($dql)
            ->setParameter('waId', $waId)->getOneOrNullResult();
    }
}
