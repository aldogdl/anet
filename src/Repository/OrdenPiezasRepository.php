<?php

namespace App\Repository;

use App\Entity\Ordenes;
use App\Entity\OrdenPiezas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OrdenPiezas|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdenPiezas|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdenPiezas[]    findAll()
 * @method OrdenPiezas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdenPiezasRepository extends ServiceEntityRepository
{
  private $result = ['abort' => false, 'msg' => 'ok', 'body' => []];

  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, OrdenPiezas::class);
  }

  /**
   * @throws ORMException
   * @throws OptimisticLockException
   */
  public function add(OrdenPiezas $entity, bool $flush = true): void
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
  public function remove(OrdenPiezas $entity, bool $flush = true): void
  {
    $this->_em->remove($entity);
    if ($flush) {
      $this->_em->flush();
    }
  }

  /** */
  public function getIdsPiezasByIdOrden(int $idOrden): \Doctrine\ORM\Query
  {
    $dql = 'SELECT p.id FROM ' . OrdenPiezas::class . ' p '.
    'WHERE p.orden = :id';
    return $this->_em->createQuery($dql)->setParameter('id', $idOrden);
  }

  /** */
  public function getPiezasByListOrdenes(String $lstOrdenes): \Doctrine\ORM\Query
  {
    $ids = explode('::', $lstOrdenes);
    $rota = count($ids);
    if($rota > 0) {
      $dql = 'SELECT p, partial o.{id} FROM ' . OrdenPiezas::class . ' p '.
      'JOIN p.orden o '.
      'WHERE p.orden IN (:ids)';
      return $this->_em->createQuery($dql)->setParameter('ids', $ids);
    }
  }

  /** */
  public function getPiezasByIdHive(int $idHive): \Doctrine\ORM\Query
  {
    $dql = 'SELECT p, partial o.{id} FROM ' . OrdenPiezas::class . ' p '.
    'JOIN p.orden o '.
    'WHERE p.idHive = :idHive';
    return $this->_em->createQuery($dql)->setParameter('idHive', $idHive);
  }

  /** */
  public function setPieza(array $data): array
  {
    $pieza = new OrdenPiezas();
    $pieza->setOrden( $this->_em->getPartialReference(Ordenes::class, $data['orden']) );
    if($data['id'] != 0) {
      if(array_key_exists('local', $data)) {
        $pieza = new OrdenPiezas();
      }else{
        $dql = $this->getPiezasByIdHive($data['id']);
        $hasPza = $dql->getResult();
        if($hasPza) {
          $pieza = $hasPza[0];
        }
      }
    }

    $pieza->setPiezaName($data['piezaName']);
    $pieza->setOrigen($data['origen']);
    $pieza->setLado($data['lado']);
    $pieza->setPosicion($data['posicion']);
    $pieza->setFotos($data['fotos']);
    $pieza->setObs($data['obs']);
    $pieza->setEst($data['est']);
    $pieza->setStt($data['stt']);
    $pieza->setIdHive($data['id']);
    try {
      $this->_em->persist($pieza);
      $this->_em->flush();
      $this->result['body']  = $pieza->getId();
    } catch (\Throwable $th) {
      $this->result['abort'] = true;
      $this->result['msg'] = $th->getMessage();
      $this->result['body']  = 'Error al guarda inténtalo nuevamente';
    }
    return $this->result;
  }

  /** */
  public function deletePiezaAntesDeSave(int $idPza): array
  {
    $pieza = null;
    $dql = $this->getPiezasByIdHive($idPza);
    $hasPza = $dql->getResult();
    if($hasPza) {
      $pieza = $hasPza[0];
    }
    if($pieza) {
      $this->result['body']['fotos'] = $pieza->getFotos();
      $this->result['body']['orden'] = $pieza->getOrden()->getId();
      try {
        $this->_em->remove($pieza);
        $this->_em->flush();
        $this->result['abort'] = false;
      } catch (\Throwable $th) {
        $this->result['abort']= true;
        $this->result['msg']  = $th->getMessage();
        $this->result['body'] = 'Error al eliminar Pieza inténtalo nuevamente';
      }
    }
    return $this->result;
  }

  /** */
  public function changeSttPiezasTo(int $idOrden, array $ruta)
  {
    $dql = 'UPDATE ' . OrdenPiezas::class . ' o '.
    'SET o.est = :est, o.stt = :stt '.
    'WHERE o.orden = :id';

    $this->_em->createQuery($dql)->setParameters([
        'id' => $idOrden,
        'est'=> $ruta['est'],
        'stt'=> $ruta['stt'],
    ])->execute();
    $this->_em->clear();
  }

  /** */
  public function getDataPiezaById(int $idPieza): \Doctrine\ORM\Query
  {
    $dql = 'SELECT p, o.id as o_id FROM ' . OrdenPiezas::class . ' p '.
    'JOIN p.orden o '.
    'WHERE p.id = :id';
    return $this->_em->createQuery($dql)->setParameter('id', $idPieza);
  }

  /**
   * from:SCP
   */
  public function getPiezasByOrden(int $idOrden): \Doctrine\ORM\Query
  {
    $dql = 'SELECT p, o.id as o_id FROM ' . OrdenPiezas::class . ' p '.
    'JOIN p.orden o '.
    'WHERE p.orden = :id';
    return $this->_em->createQuery($dql)->setParameter('id', $idOrden);
  }

  /**
   * from:Harbi
   */
  public function getAllOrdsPzas(array $idsOrden): \Doctrine\ORM\Query
  {
    $dql = 'SELECT p, o, partial c.{id} FROM ' . OrdenPiezas::class . ' p '.
    'JOIN p.orden o '.
    'JOIN o.own c '.
    'WHERE p.orden IN (:ids)';
    return $this->_em->createQuery($dql)->setParameter('ids', $idsOrden);
  }
}
