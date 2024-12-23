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

}
