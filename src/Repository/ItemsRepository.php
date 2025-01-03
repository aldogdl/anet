<?php

namespace App\Repository;

use App\Entity\Items;
use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Google\Auth\Cache\Item;

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
    public function getItemById(int $id): \Doctrine\ORM\Query
    {   
        $dql = 'SELECT it FROM ' . Items::class . ' it '.
        $dql = 'WHERE it.id = :id';
        return $this->_em->createQuery($dql)->setParameters(['id' => $id]);
    }

    /** 
     * Tomamos una lista de Items como referencia, es decir una lista con los
     * campos básicos para una presentacion rapida, como listas de busqueda, etc.
    */
    public function getItemsAsRefByType(String $type): \Doctrine\ORM\Query {

        $dql = 'SELECT partial it.{id, pieza, lado, poss, marca, model, anios, ownWaId, '.
        'ownSlug, thumbnail, stt, idItem, fotos, createdAt} FROM '.Items::class.' it '.
        'WHERE it.type = :tipo '.
        'ORDER BY it.id DESC';
        return $this->_em->createQuery($dql)->setParameter('tipo', $type);
    }

    /** 
     * Tomamos una lista de Items con todos los campos donde el waId paraso por
     * parametro sean diferentes a este, tomando los items que pertenecen a otros usuarios
    */
    public function getItemsCompleteByType(String $type, String $waIdFrom = ''): \Doctrine\ORM\Query
    {
        $dql = 'SELECT it FROM '.Items::class.' it '.
        'WHERE it.ownWaId != :waIdFrom AND it.type = :tipo '.
        'ORDER BY it.id DESC';

        return $this->_em->createQuery($dql)->setParameters(['tipo' => $type, 'waIdFrom' => $waIdFrom]);
    }

    /** */
    public function setItem(array $item): ?int
    {
        $query = $this->getItemByIdItemAndWaId($item['idItem'], $item['ownWaId']);
        $obj = $query->getOneOrNullResult();
        if($obj == null) {
            $obj = new Items();
        }

        $obj->fromMapItem($item);
        try {
            $this->add($obj, true);
            return $obj->getId();
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

    /** */
    public function getItemByIdCot(String $idCot): \Doctrine\ORM\Query
    {   
        $dql = 'SELECT it FROM ' . Items::class . ' it '.
        $dql = 'WHERE it.idCot = :idCot';
        return $this->_em->createQuery($dql)->setParameter('idCot', $idCot);
    }

    /** */
    public function getItemByIdItemAndWaId(String $idItem, String $waId): \Doctrine\ORM\Query
    {   
        $dql = 'SELECT it FROM ' . Items::class . ' it '.
        $dql = 'WHERE it.idItem = :idItem AND it.ownWaId = :waId';
        return $this->_em->createQuery($dql)->setParameters(['idItem' => $idItem, 'waId' => $waId]);
    }

    /** 
     * Cuando un proveedor cotiza una solicitud, aqui guardamos esa cotizacion convertida
     * a inventario, este proceso se realiza en ComCore y desde ahi se envia a la ruta
     * com-core/cotizacion/
    */
    public function setItemOfCotizacion(array $itemMap) :int
    {
        $item = $this->getItemByIdCot($itemMap['idCot'])->getResult();
        if(!$item) {
            $item = new Items();
        }
        
        try {
            $item = $item->fromMapItem($itemMap);
            $this->add($item, true);
            return $item->getId();
        } catch (\Throwable $th) {
            return 0;
        }
    }

}
