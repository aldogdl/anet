<?php

namespace App\Repository;

use App\Entity\NG1Empresas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NG1Empresas|null find($id, $lockMode = null, $lockVersion = null)
 * @method NG1Empresas|null findOneBy(array $criteria, array $orderBy = null)
 * @method NG1Empresas[]    findAll()
 * @method NG1Empresas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NG1EmpresasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NG1Empresas::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(NG1Empresas $entity, bool $flush = true): void
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
    public function remove(NG1Empresas $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

}
