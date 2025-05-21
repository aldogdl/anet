<?php

namespace App\Repository;

use App\Entity\MMEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MMEntity>
 *
 * @method MMEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method MMEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method MMEntity[]    findAll()
 * @method MMEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MMEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MMEntity::class);
    }

    /** */
    public function getMarcaById(int $id) : \Doctrine\ORM\Query
    {
        $dql = 'SELECT m FROM '. MMEntity::class .' m '.
        'WHERE m.id = :id';
        return $this->_em->createQuery($dql)->setParameter('id', $id);
    }
    
    /** */
    public function existMarcaByName(String $name) : bool
    {
        $dql = 'SELECT COUNT(m.id) FROM '. MMEntity::class .' m '.
        'WHERE m.name = :name';

        return $this->_em->createQuery($dql)
            ->setParameter('name', $name)
            ->getSingleScalarResult() > 0;
    }

    /** */
    public function setMarca(array $marca) : array
    {
        $obj = new MMEntity();
        $obj = $obj->fromJson($marca);
        if(!$this->existMarcaByName($obj->getName())) {
            try {
                $this->_em->persist($obj);
                $this->_em->flush();
            } catch (\Throwable $th) {
                return $this->json(['abort' => true, 'body' => $th->getMessage()]);
            }
        }

        return ['abort' => false, 'body' => 'Guardao con éxito'];
    }
}
