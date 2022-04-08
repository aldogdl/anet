<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;
use App\Entity\NG2Contactos;
use App\Entity\Ordenes;
use App\Service\StatusRutas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Ordenes|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ordenes|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ordenes[]    findAll()
 * @method Ordenes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdenesRepository extends ServiceEntityRepository
{

    private $rutas;

    public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

    public function __construct(ManagerRegistry $registry, StatusRutas $rtas)
    {
        parent::__construct($registry, Ordenes::class);
        $this->rutas = $rtas;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Ordenes $entity, bool $flush = true): void
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
    public function remove(Ordenes $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /** ------------------------------------------------------------------- */
    
    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function setOrden(array $orden): array
    {   
        if($orden['id'] != 0) {
            $entity = $this->_em->find(Ordenes::class, $orden['id']);
        }else{
            $entity = new Ordenes();
            $entity->setOwn( $this->_em->getPartialReference(NG2Contactos::class, $orden['own']) );
        }
        
        $entity->setMarca( $this->_em->getPartialReference(AO1Marcas::class, $orden['id_marca']) );
        $entity->setModelo( $this->_em->getPartialReference(AO2Modelos::class, $orden['id_modelo']) );
        $entity->setAnio($orden['anio']);
        $entity->setIsNac($orden['is_nacional']);
        $entity->setEst($orden['est']);
        $entity->setStt($orden['stt']);
        $entity->setRuta($this->rutas->getLastRutaName());

        $this->_em->persist($entity);
        try {
            $this->_em->flush();
            $this->result['body'] = [
                'id' => $entity->getId(),
                'ruta' => $entity->getRuta(),
                'created_at' => $entity->getCreatedAt(),
            ];
        } catch (\Throwable $th) {
            $this->result['abort'] = true;
            $this->result['msg'] = $th->getMessage();
            $this->result['body'] = 'Error al capturar la Orden, Inténtalo nuevamente por favor.';
        }

        return $this->result;
    }

    /**
     * 
    */
    public function getOrdenesByOwnAndEstacion(int $idUser, String $est): \Doctrine\ORM\Query
    {   
        $dql = 'SELECT o, partial mk.{id}, partial md.{id}, partial a.{id}, partial u.{id} FROM ' . Ordenes::class . ' o '.
        'JOIN o.marca mk '.
        'JOIN o.modelo md '.
        'LEFT JOIN o.avo a '.
        'JOIN o.own u '.
        'WHERE o.own = :own AND o.est = :est '.
        'ORDER BY o.id ASC';
        return $this->_em->createQuery($dql)->setParameters(['own' => $idUser, 'est'=> $est]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeOrden(int $idOrden): array
    {   
        $entity = $this->_em->find(Ordenes::class, $idOrden);
        if($entity) {
            $this->_em->remove($entity);
            $this->_em->flush();
        }
        return ['abort' => false, 'body' => ['ok']];
    }

    /** */
    public function changeSttOrdenTo(int $idOrden, array $estStt)
    {
        $dql = 'UPDATE ' . Ordenes::class . ' o '.
        'SET o.est = :est, o.stt = :stt '.
        'WHERE o.id = :id';
        $this->_em->createQuery($dql)->setParameters([
            'id'  => $idOrden,
            'est' => $estStt['est'],
            'stt' => $estStt['stt'],
        ])->execute();
        $this->_em->clear();
    }

    /**
     * from:SCP
     */
    public function getAllOrdenByAvo(int $idAvo): \Doctrine\ORM\Query
    {
        $dql = 'SELECT o, mk, md, '.
        'partial u.{id, nombre, cargo, celular, roles}, '.
        'partial e.{id, nombre} '.
        'FROM ' . Ordenes::class . ' o '.
        'JOIN o.marca mk '.
        'JOIN o.modelo md '.
        'JOIN o.own u '.
        'JOIN u.empresa e ';
        if($idAvo != 0) {
            $dql = $dql . 'WHERE o.id = :id ';
            return $this->_em->createQuery($dql)->setParameter('id', $idAvo);
        }
        $dql = $dql . 'ORDER BY o.id DESC';
        return $this->_em->createQuery($dql);
    }

    /**
     * from:Centinela, SCP
     */
    public function getDataOrdenById(string $idOrden): \Doctrine\ORM\Query
    {
        $dql = 'SELECT o, mk, md, '.
        'partial u.{id, nombre, cargo, celular, roles}, '.
        'partial e.{id, nombre} '.
        'FROM ' . Ordenes::class . ' o '.
        'JOIN o.marca mk '.
        'JOIN o.modelo md '.
        'JOIN o.own u '.
        'JOIN u.empresa e '.
        'WHERE o.id = :id ';
        return $this->_em->createQuery($dql)->setParameter('id', $idOrden);
    }
}
