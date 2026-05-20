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

//    /**
//     * @return SysCom[] Returns an array of SysCom objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SysCom
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
