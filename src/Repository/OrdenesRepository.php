<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;
use App\Entity\NG2Contactos;
use App\Entity\Ordenes;
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
    public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ordenes::class);
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

      $this->_em->persist($entity);
      try {
        $this->_em->flush();
        $this->result['body'] = [
          'id' => $entity->getId(),
          'created_at' => $entity->getCreatedAt(),
        ];
      } catch (\Throwable $th) {
        $this->result['abort']= true;
        $this->result['msg']  = $th->getMessage();
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
        'JOIN u.empresa e '.
        'WHERE o.avo ';
        if($idAvo == 0) {
            $dql = $dql . 'is NULL ORDER BY o.id DESC';
            return $this->_em->createQuery($dql);
        }else{
            $dql = $dql . '= :avo ORDER BY o.id DESC';
            return $this->_em->createQuery($dql)->setParameter('avo', $idAvo);
        }
    }

    /**
     * from => :Centinela, :SCP
     */
    public function getDataOrdenById(string $idOrden, $withOwn = true): \Doctrine\ORM\Query
    {

      $dql = 'SELECT o, mk, md ';
      if($withOwn) {
        $dql = $dql . ', partial u.{id, nombre, cargo, celular, roles}, partial e.{id, nombre} ';
      }
      $dql = $dql . 'FROM ' . Ordenes::class . ' o '.
      'JOIN o.marca mk '.
      'JOIN o.modelo md ';
      if($withOwn) {
        $dql = $dql . 'JOIN o.own u JOIN u.empresa e ';
      }
      $dql = $dql . 'WHERE o.id = :id ';
      return $this->_em->createQuery($dql)->setParameter('id', $idOrden);
    }

    /**
    *
    */
    public function getTargetById(int $id): array
    {
      $dql = $this->getDataOrdenById((string) $id, false);
      return $dql->getArrayResult();
    }

    /**
     * from => :SCP
     */
    public function asignarOrdenesToAvo(int $idAvo, array $ordenes): array
    {
        $avo = $this->_em->getPartialReference(NG2Contactos::class, $idAvo);
        if($avo) {

            $dql = 'UPDATE ' . Ordenes::class . ' o '.
            'SET o.avo = :avoNew WHERE o.id IN (:idsOrdenes)';
            try {
                $this->_em->createQuery($dql)->setParameters([
                    'avoNew' => $avo, 'idsOrdenes' => $ordenes
                ])->execute();
                $this->_em->clear();
            } catch (\Throwable $th) {
                $this->result['abort'] = true;
                $this->result['msg'] = $th->getMessage();
                $this->result['body'] = 'ERROR, al Guardar la Asignación';
            }
        }else{
            $this->result['abort'] = true;
            $this->result['msg'] = 'error';
            $this->result['body'] = 'ERROR, No se encontró el AVO ' . $idAvo;
        }
        return $this->result;
    }
}
