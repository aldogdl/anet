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
    public function getById(int $id) : \Doctrine\ORM\Query
    {
        $dql = 'SELECT m FROM '. MMEntity::class .' m '.
        'WHERE m.id = :id';
        return $this->_em->createQuery($dql)->setParameter('id', $id);
    }
    
    /** 
     * Revisamos si existe un elemento por medio de su nombre
    */
    public function existByName(String $name) : bool
    {
        $dql = 'SELECT COUNT(m.id) FROM '. MMEntity::class .' m '.
        'WHERE m.name = :name';

        return $this->_em->createQuery($dql)
            ->setParameter('name', $name)->getSingleScalarResult() > 0;
    }
    
    /** 
     * Recuperamos todos los elementos de manera slim
    */
    public function getMMSlim(String $tipo) : array
    {
        $dql = 'SELECT m FROM '. MMEntity::class .' m ';
        if($tipo == 'models') {
            $dql = $dql . 'WHERE m.idMrk != 0';
        }else{
            $dql = $dql . 'WHERE m.idMrk = 0';
        }

        $dql = $dql . ' ORDER BY m.name ASC';
        $res = $this->_em->createQuery($dql)->getArrayResult();
        
        $result = [];
        $rota = count($res);
        if($rota > 0) {
            for ($i=0; $i < $rota; $i++) { 
                $result[] = [
                    'i' => $res[$i]['id'],
                    'im'=> $res[$i]['idMrk'],
                    'n' => $res[$i]['name'],
                    'v' => $res[$i]['variants'],
                ];
            }
        }
        file_put_contents($tipo.'_slim.json', json_encode($result));
        return $result;
    }
    
    /** 
     * Recuperamos todos los elementos en caso de que $idMrk == null
     * se refiere a las marcas en caso contrario son modelos
    */
    public function getMM(?int $idMrk) : array
    {
        $dql = 'SELECT m FROM '. MMEntity::class .' m ';
        if($idMrk != null) {
            $dql = $dql . 'WHERE m.idMrk = :idMrk';
        }else{
            $dql = $dql . 'WHERE m.idMrk = 0';
        }

        $dql = $dql . ' ORDER BY m.id ASC';
        if($idMrk != null) {
            return $this->_em->createQuery($dql)
                ->setParameter('idMrk', $idMrk)->getArrayResult();
        }

        return $this->_em->createQuery($dql)->getArrayResult();
    }

    /** 
     * Guardamos el elemento ya sea marca o modelo
    */
    public function setMM(array $mm) : array
    {
        $obj = new MMEntity();
        $obj = $obj->fromJson($mm);
        if(!$this->existByName($obj->getName())) {
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
