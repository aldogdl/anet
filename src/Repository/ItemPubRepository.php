<?php

namespace App\Repository;

use App\Entity\ItemPub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemPub>
 *
 * @method ItemPub|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemPub|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemPub[]    findAll()
 * @method ItemPub[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemPubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemPub::class);
    }

    /** */
    public function existPub(String $idSrc): int
    {
        $dql = 'SELECT COUNT(it.id) FROM ' . ItemPub::class . ' it '.
        'WHERE it.idSrc = :idSrc';

        return $this->_em->createQuery($dql)
            ->setParameter('idSrc', $idSrc)->getSingleScalarResult();
    } 

    /** */
    public function setPub(array $data): array
    {

        $existe = $this->existPub($data['idSrc']);
        $action = 'add';
        $result = [];
        if($existe == 0) {
            $obj = new ItemPub();
            $obj = $obj->fromJson($data);
            try {
                $this->_em->persist($obj);
                $this->_em->flush();
                $result = $obj->toSlim();
                $result['id'] = $obj->getId();
            } catch (\Throwable $th) {
                return ['abort' => true, "body" => $th->getMessage()];
            }
        }else{
            $action = 'edt';
            $result['id'] = $existe;
        }
        
        return ['abort' => false, "action" => $action, "body" => $result];
    }
}
