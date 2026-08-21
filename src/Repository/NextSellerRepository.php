<?php

namespace App\Repository;

use App\Entity\NextSeller;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NextSeller>
 *
 * @method NextSeller|null find($id, $lockMode = null, $lockVersion = null)
 * @method NextSeller|null findOneBy(array $criteria, array $orderBy = null)
 * @method NextSeller[]    findAll()
 * @method NextSeller[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NextSellerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NextSeller::class);
    }

//    /**
//     * @return NextSeller[] Returns an array of NextSeller objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('n.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?NextSeller
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
