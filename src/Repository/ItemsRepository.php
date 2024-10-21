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
    public function getLastItems(?int $lastTime, ?int $lasId): \Doctrine\ORM\Query
    {   
        $data = 1;
        $dql = 'SELECT it FROM ' . Items::class . ' it ';
        if($lastTime != null) {
            $lastTime = (int) ($lastTime / 1000);
            $data = \DateTimeImmutable::createFromFormat('U', $lastTime);
            $dql = $dql . 'WHERE it.createdAt > :data ';
        }
        if($lasId != null) {
            $data = $lasId;
            $dql = $dql . 'WHERE it.id > :data ';
        }
        $dql = $dql . 'ORDER BY it.createdAt DESC';
        return $this->_em->createQuery($dql)->setParameter('data', $data);
    }

    /** 
     * Cuando un proveedor cotiza una solicitud, aqui guardamos esa cotizacion convertida
     * a inventario, este proceso se realiza en ComCore y desde ahi se envia a la ruta
     * com-core/cotizacion/
    */
    public function setItemOfCotizacion(array $itemMap) :int
    {
        $item = new Items();

        file_put_contents('error.log_1.txt', '');
        try {
            $item->fromMapItem($itemMap);
            $this->add($item, true);
            return $item->getId();
        } catch (\Throwable $th) {
            file_put_contents('error.log.txt', $th->getMessage());
            return 0;
        }
    }

}
