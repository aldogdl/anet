<?php

namespace App\Repository;

use App\Entity\Campaings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Campaings|null find($id, $lockMode = null, $lockVersion = null)
 * @method Campaings|null findOneBy(array $criteria, array $orderBy = null)
 * @method Campaings[]    findAll()
 * @method Campaings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CampaingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campaings::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Campaings $entity, bool $flush = true): void
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
    public function remove(Campaings $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /***/
    public function getCampaignBySlug(string $slug): \Doctrine\ORM\Query
    {
      $dql = 'SELECT c FROM ' . Campaings::class . ' c '.
      'WHERE c.slug = :slug';
      return $this->_em->createQuery($dql)->setParameter('slug', $slug);
    }

    /***/
    public function getIdCampaingBySlug(string $slug): \Doctrine\ORM\Query
    {
      $dql = 'SELECT partial c.{id} FROM ' . Campaings::class . ' c '.
      'WHERE c.slug = :slug';
      return $this->_em->createQuery($dql)->setParameter('slug', $slug);
    }
}
