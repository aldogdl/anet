<?php

namespace App\Repository;

use App\Entity\AO2Modelos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AO2Modelos|null find($id, $lockMode = null, $lockVersion = null)
 * @method AO2Modelos|null findOneBy(array $criteria, array $orderBy = null)
 * @method AO2Modelos[]    findAll()
 * @method AO2Modelos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AO2ModelosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AO2Modelos::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(AO2Modelos $entity, bool $flush = true): void
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
    public function remove(AO2Modelos $entity, bool $flush = true): void
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
    public function getAllModelosByIdMarca(int $idMarca): \Doctrine\ORM\Query
    {
        $dql = 'SELECT md, partial mrk.{id} FROM ' . AO2Modelos::class . ' md '.
        'JOIN md.marca mrk '.
        'WHERE md.marca = :idMarca '.
        'ORDER BY md.nombre ASC';

        return $this->_em->createQuery($dql)->setParameter('idMarca', $idMarca);
    }


}
