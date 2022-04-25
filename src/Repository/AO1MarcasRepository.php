<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AO1Marcas|null find($id, $lockMode = null, $lockVersion = null)
 * @method AO1Marcas|null findOneBy(array $criteria, array $orderBy = null)
 * @method AO1Marcas[]    findAll()
 * @method AO1Marcas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AO1MarcasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AO1Marcas::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function getAllAsArray(): array
    {
        return $this->_em->createQuery(
            'SELECT mk FROM ' . AO1Marcas::class . ' mk '
        )->getScalarResult();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(AO1Marcas $entity, bool $flush = true): void
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
    public function remove(AO1Marcas $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function getAllAutos(): \Doctrine\ORM\Query
    {
        $dql = 'SELECT mrk, partial md.{id, nombre} FROM ' . AO1Marcas::class . ' mrk '.
        'JOIN mrk.modelos md '.
        'ORDER BY mrk.nombre ASC';

        return $this->_em->createQuery($dql);
    }

}
