<?php

namespace App\Repository;

use App\Entity\Items;
use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Items>
 *
 * @method Items|null find($id, $lockMode = null, $lockVersion = null)
 * @method Items|null findOneBy(array $criteria, array $orderBy = null)
 * @method Items[]    findAll()
 * @method Items[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Items::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Items $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /** */
    public function setProduct(array $product): ?int
    {
        $item = new Items();
        $item->fromMap($product);
        try {
            $this->add($item, true);
            return $item->getId();
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /** */
    public function getLastItems(int $lastTime): \Doctrine\ORM\Query
    {

        $dql = 'SELECT it FORM ' . Items::class . ' it '.
        'WHERE it.createdAt > :fecha';

        return $this->_em->createQuery($dql)->setParameter(
            'fecha', \DateTimeImmutable::createFromFormat('U', $lastTime)
        );
    }
}
