<?php

namespace App\Repository;

use App\Entity\ScmReceivers;
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

    // /**
    //  * @return ScmReceivers[] Returns an array of ScmReceivers objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ScmReceivers
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
