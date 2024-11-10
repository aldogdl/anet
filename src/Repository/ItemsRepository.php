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
    public function getItemById(int $id): \Doctrine\ORM\Query
    {   
        $dql = 'SELECT it FROM ' . Items::class . ' it '.
        $dql = 'WHERE it.id = :id';
        return $this->_em->createQuery($dql)->setParameters(['id' => $id]);
    }

    /** */
    public function setProduct(array $product): ?int
    {
        $item = new Items();
        if(array_key_exists('attrs', $product)) {
            $item->fromMap($product);
        }else{
            $query = $this->getItemByIdItemAndWaId($product['idItem'], $product['ownWaId']);
            $result = $query->getResult();
            if($result) {
                $item = $result[0];
            }
            $item->fromMapItem($product);
        }
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

    /**
     * Uso temporal, el objetico es convertir el nuevo map de un ITEM a viejo
     * json, esto con la finalidad de no reprogramar ComCore
    */
    public function parseItem(array $item): array
    {

        $title = $item['pieza'];
        $token = $item['pieza'];
        if($item['lado'] != '') {
            $title = $title . ' ' . $item['lado'];
            $token = $title;
        }
        if($item['poss'] != '') {
            $title = $title . ' ' . $item['poss'];
            $token = $title;
        }
        if($item['marca'] != '') {
            $title = $title . ' PARA ' . $item['marca'];
            $token = $token . ' ' . $item['marca'];
        }
        if($item['modelo'] != '') {
            $title = $title . ' ' . $item['modelo'];
            $token = $token . ' ' . $item['modelo'];
        }
        $anios = count($item['anios']);
        if($anios > 0) {
            $title = $title . ' APLICA A ' . $item['anios'][0];
            $token = $token . ' ' . $item['anios'][0];
        }
        if($anios > 1) {
            $title = $title . '-' . $item['anios'][1];
            $token = $token . '-' . $item['anios'][1];
        }

        return [
            'product' => [
                'id' => $item['id'],
                'uuid' => $item['idItem'],
                'src' => $item['source'],
                'title' => strtoupper($title),
                'token' => strtolower($token),
                'permalink' => $item['permalink'],
                'thumbnail' => $item['thumbnail'],
                'fotos' => $item['fotos'],
                'detalles' => $item['condicion'],
                'price' => $item['costo'],
                'originalPrice' => $item['price'],
                'sellerId' => $item['ownMlId'],
                'sellerSlug' => $item['ownSlug'],
                'attrs' => [
                    'pieza' => $item['pieza'],
                    'lado' => $item['lado'],
                    'poss' => $item['poss'],
                    'marcaId' => $item['mrkId'],
                    'marca' => $item['marca'],
                    'modeloId' => $item['mdlId'],
                    'modelo' => $item['modelo'],
                    'anios' => $item['anios'],
                    'origen' => $item['origen'],
                    'slug' => $item['ownSlug'],
                    'waId' => $item['ownWaId'],
                    'sku' => $item['idItem'],
                    'isVip' => true,
                ],
                'isVendida' => false,
                'isFav' => false,
            ],
            'eventName' => "anet_shop",
            'subEvent' => $item['type']
        ];
    }

}
