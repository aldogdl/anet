<?php

namespace App\Repository;

use App\Entity\NG2Contactos;
use App\Entity\Ordenes;
use App\Entity\ScmOrdpza;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScmOrdpza|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScmOrdpza|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScmOrdpza[]    findAll()
 * @method ScmOrdpza[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScmOrdpzaRepository extends ServiceEntityRepository
{

    public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScmOrdpza::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(ScmOrdpza $entity, bool $flush = true): void
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
    public function remove(ScmOrdpza $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }


    /*
    public function findOneBySomeField($value): ?ScmOrdpza
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
