<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;
use App\Entity\NG2Contactos;
use App\Entity\Ordenes;
use App\Service\WebHook;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
      if(!$entity) {
        if(array_key_exists('local', $orden)) {
          $entity = new Ordenes();
          $entity->setOwn( $this->_em->getPartialReference(NG2Contactos::class, $orden['own']) );
        }
      }else{
        $this->result['abort'] = true;
        $this->result['msg'] = 'La Orden '.$orden['id'].' ya existe, no se guardaron los datos';
        $this->result['body']= 'Error al guarda la orden '.$orden['id'];
        return $this->result;
      }
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
        'created_at' => $entity->getCreatedAt()
      ];

    } catch (\Throwable $th) {
      $this->result['abort']= true;
      $this->result['msg']  = $th->getMessage();
      $this->result['body'] = 'Error al capturar la Orden, Inténtalo nuevamente por favor.';
    }

    if(array_key_exists('local', $orden)) {
      $this->revisarIdTable($entity, $orden['id']);
    }
    return $this->result;
  }

  /**
   * Hacemos el guardado de la orden en archivo para nifi
  */
  public function buildForNifiAndSendEvent(int $idOrden, String $pathNifi, WebHook $wh): void
  {

    $resWh = ['abort' => true, 'msg' => ''];
    $entity = $this->_em->find(Ordenes::class, $idOrden);
    if(!$entity){ $resWh['msg'] = 'No se encontró la orden '.$idOrden; }

    if($resWh['msg'] == '') {
      
      $filename = $pathNifi.$entity->getId().'.json';
      $file = $entity->toArray();
      if(count($file) == 0) {
        $resWh['msg'] = 'No se recuperó el array de la orden '.$idOrden;
      }

      if($resWh['msg'] == '') {

        $content = file_put_contents($filename, json_encode($file));
        if($content == 0) {
          $resWh['msg'] = 'No se guardo correctamente en el archivo la orden '.$idOrden;
        }
      }

      if($resWh['msg'] == '') {
        $isOk = false;
        $resWh = $wh->sendMy('creada_solicitud', $filename);
        $isOk = !$resWh['abort'];
        if(!$isOk) {
          file_put_contents( $pathNifi.'fails/'.$filename,  json_encode($resWh) );
        }
      }
    }
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
  public function changeSttOrdenTo(array $idOrden, array $estStt)
  {
    $dql = 'UPDATE ' . Ordenes::class . ' o '.
    'SET o.est = :est, o.stt = :stt '.
    'WHERE o.id IN (:id)';
    
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
  public function getAllIdsOrdenByAvo(int $idAvo): \Doctrine\ORM\Query
  {
    $dql = 'SELECT partial o.{id, est} FROM ' . Ordenes::class . ' o '.
    'WHERE o.avo = :avo ORDER BY o.id ASC';
    return $this->_em->createQuery($dql)->setParameter('avo', $idAvo);
  }

  /**
   * from:SCP
   */
  public function getAllOrdenByAvo(int $idAvo): \Doctrine\ORM\Query
  {
    $dql = 'SELECT o, mk, md, '.
    'partial u.{id, nombre, cargo, celular, roles}, '.
    'partial e.{id, nombre}, partial p.{id} '.
    'FROM ' . Ordenes::class . ' o '.
    'JOIN o.marca mk '.
    'JOIN o.modelo md '.
    'JOIN o.own u '.
    'JOIN o.piezas p '.
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
   * from => :Centinela, :SCP, :Harbi
   */
  public function getDataOrdenById(
    string $idOrden, bool $withOwn = true, bool $withPza = false
  ): \Doctrine\ORM\Query
  {
    $dql = 'SELECT o, mk, md ';
    if($withOwn) {
      $dql = $dql . ', partial u.{id, nombre, cargo, celular, roles}, partial e.{id, nombre}';
    }
    $dql = $dql . ', partial a.{id, curc} ';
    if($withPza) {
      $dql = $dql . ', partial pzs.{id, piezaName, origen} ';
    }
    $dql = $dql . 'FROM ' . Ordenes::class . ' o '.
    'JOIN o.marca mk '.
    'JOIN o.modelo md '.
    'LEFT JOIN o.avo a ';
    
    if($withOwn) {
      $dql = $dql . 'JOIN o.own u JOIN u.empresa e ';
    }
    if($withPza) {
      $dql = $dql . 'JOIN o.piezas pzs ';
    }
    $dql = $dql . 'WHERE o.id = :id ';
    return $this->_em->createQuery($dql)->setParameter('id', $idOrden);
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

  /**
   * @param $ntg son todas ordenes que el cotizador a indicado como no tengo
  */
  public function fetchCarnadaByAnsuelo(array $data, array $ntg): array
  {
    // Recuperar los filtros del cotizador
    $r = $this->fetchCarnada($data, $ntg['pzas']);
    return $r;
  }

  /**
   * 
  */
  private function fetchCarnada(array $data, array $pizas): array
  {
    $has = false;
    if(array_key_exists('md', $data)) {

      $findedIn = 'Modelo';
      $dql = 'SELECT partial o.{id}, partial p.{id} FROM ' . Ordenes::class . ' o ' .
      'JOIN o.piezas p '.
      'WHERE o.est = :est AND o.modelo = :md '.
      'AND o.id NOT LIKE :idOrd AND p.origen NOT LIKE :origen AND p.id NOT IN (:pzas) '.
      'ORDER BY o.id DESC';
      $has = $this->_em->createQuery($dql)->setParameters([
        'est' => '3', 'md' => $data['md'], 'idOrd' => $data['ido'],
        'origen' => 'GENÉRICA%', 'pzas' => $pizas
      ])->execute();
    }

    if(!$has) {
      if(array_key_exists('mk', $data)) {

        $findedIn = 'Marca';
        $dql = 'SELECT partial o.{id}, partial p.{id} FROM ' . Ordenes::class . ' o ' .
        'JOIN o.piezas p '.
        'WHERE o.est = :est AND o.marca = :mk AND o.id NOT LIKE :idOrd AND '.
        'p.origen NOT LIKE :origen AND p.id NOT IN (:pzas) '.
        'ORDER BY o.id DESC';
        $has = $this->_em->createQuery($dql)->setParameters([
          'est' => '3', 'mk' => $data['mk'], 'idOrd' => $data['ido'],
          'origen' => 'GENÉRICA%', 'pzas' => $pizas
        ])->execute();
      }
    }
    // 
    if(!$has) {
      $findedIn = 'Otros';
      $dql = 'SELECT partial o.{id}, partial p.{id} FROM ' . Ordenes::class . ' o ' .
      'JOIN o.piezas p '.
      'WHERE o.est = :est AND o.id NOT LIKE :idOrd AND p.origen NOT LIKE :origen '.
      'AND p.id NOT IN (:pzas) ORDER BY o.id DESC';
      $has = $this->_em->createQuery($dql)->setParameters([
        'est' => '3', 'idOrd' => $data['ido'], 'origen' => 'GENÉRICA%', 'pzas' => $pizas
      ])->execute();
    }

    if($has) {
      $dql = $this->getOrdenAndPieza($has[0]->getId());
      $ord = $dql->getArrayResult();
      return [ 'from' => $findedIn, 'orden' => $ord[0] ];
    }
    
    return [];
  }

  /** */
  public function getOrdenAndPieza(int $id): \Doctrine\ORM\Query
  {
    $auto = 'partial %s.{id, nombre %s}, ';
    $ct = 'partial %s.{id, curc, nombre, celular}, ';
    $own = sprintf($ct, 'c');
    $avo = sprintf($ct, 'a');
    $mrk = sprintf($auto, 'mk', ', logo');
    $mdl = sprintf($auto, 'md', '');
    $e = 'partial e.{id, nombre} ';

    $dql = 'SELECT o, p, '.$own.$avo.$mrk.$mdl.$e.'FROM ' . Ordenes::class . ' o ' .
    'JOIN o.marca mk '.
    'JOIN o.modelo md '.
    'JOIN o.own c '.
    'JOIN o.avo a '.
    'JOIN c.empresa e '.
    'JOIN o.piezas p '.
    'WHERE o.id = :id '.
    '';

    return $this->_em->createQuery($dql)->setParameter('id', $id);
  }

  /**
   * Usado para recuperar todas las ordenes en la app de cotizo las cuales estan
   * publicadas para su cotización por parte del Cotizador.
  */
  public function getOrdenesAndPiezas(String $callFrom): \Doctrine\ORM\Query
  {
    $stt1 = '1';
    $stt2 = '2';
    
    $stt = 'o.stt = :stt1 OR o.stt = :stt2 ';
    $values = ['stt1' => $stt1, 'stt2' => $stt2];
    if($callFrom == 'home') {
      $stt = 'o.stt = :stt2 ';
      $values = ['stt2' => $stt2];
    }

    $auto = 'partial %s.{id, nombre %s}, ';
    $ct = 'partial %s.{id, curc, nombre, celular}, ';
    $own = sprintf($ct, 'c');
    $avo = sprintf($ct, 'a');
    $mrk = sprintf($auto, 'mk', ', logo');
    $mdl = sprintf($auto, 'md', '');
    $e = 'partial e.{id, nombre} ';

    $dql = 'SELECT o, p, '.$own.$avo.$mrk.$mdl.$e.'FROM ' . Ordenes::class . ' o ' .
    'JOIN o.marca mk '.
    'JOIN o.modelo md '.
    'JOIN o.own c '.
    'JOIN o.avo a '.
    'JOIN c.empresa e '.
    'JOIN o.piezas p '.
    'WHERE o.est > 2 AND o.est < 6 AND '. $stt .
    'ORDER BY o.id DESC';

    return $this->_em->createQuery($dql)->setParameters($values);
  }

  /** */
  public function getOrdenesAndPiezasApartadas(array $data): \Doctrine\ORM\Query
  {

    $ords = [];
    $pzas = [];
    $rota = count($data);
    for ($i=0; $i < $rota; $i++) { 
      $ords[] = $data[$i]['ord'];
      $pzas = array_merge($data[$i]['pza'], $pzas);
    }
    
    $auto = 'partial %s.{id, nombre %s}, ';
    $ct = 'partial %s.{id, curc, nombre, celular}, ';
    $own = sprintf($ct, 'c');
    $avo = sprintf($ct, 'a');
    $mrk = sprintf($auto, 'mk', ', logo');
    $mdl = sprintf($auto, 'md', '');
    $e = 'partial e.{id, nombre} ';

    $dql = 'SELECT o, p, '.$own.$avo.$mrk.$mdl.$e.'FROM ' . Ordenes::class . ' o ' .
    'JOIN o.marca mk '.
    'JOIN o.modelo md '.
    'JOIN o.own c '.
    'JOIN o.avo a '.
    'JOIN c.empresa e '.
    'JOIN o.piezas p WITH p.id IN (:pzas) '.
    'WHERE o.id IN (:ords) AND o.est = 3 '.
    'ORDER BY o.id DESC';

    return $this->_em->createQuery($dql)->setParameters([
      'ords' => $ords, 'pzas' => $pzas
    ]);
  }

  ///
  private function revisarIdTable(Ordenes $ord, int $id)
  {
    if($ord->getId() != $id) {
      $dql = 'UPDATE ' . Ordenes::class . ' e '.
      'SET e.id = :idN '.
      'WHERE e.id = :id';
      try {
        $this->_em->createQuery($dql)->setParameters([
          'idN' => $id, 'id' => $ord->getId()
        ])->execute();
      } catch (\Throwable $th) {
        $this->result['abort'] = true;
        $this->result['msg'] = $th->getMessage();
        $this->borrarAndCompactar($ord);
      } 
    }
  }

  /** */
  public function borrarAndCompactar(Ordenes $obj)
  {
    $this->_em->remove($obj);

    $dql = 'SELECT COUNT(o.id) FROM ' . Ordenes::class . ' o ';
    $res = $this->_em->createQuery($dql)->getSingleScalarResult();
    if($res > 0) {
      $connDb = $this->getEntityManager()->getConnection();
      $connDb->prepare('ALTER TABLE '.Ordenes::class.' AUTO_INCREMENT = '.$res.';')->executeStatement();
    }
  }

  ///
  public function paginador(\Doctrine\ORM\Query $query, int $page = 1, $mode = 'scalar', int $limit = 19): array
  {
    if($mode == 'array') {
      $query->setHydrationMode(Query::HYDRATE_ARRAY);
    }else{
      $query->setHydrationMode(Query::HYDRATE_SCALAR);
    }
    $query = $query->setFirstResult($limit * ($page - 1))->setMaxResults($limit);
    $pag = new Paginator($query);
    $totalItems = $pag->count();
    $pagesCount = ceil($totalItems / $limit);
    return [
      'data' => ['total' => $totalItems, 'tpages'=> $pagesCount], 'results' => $pag
    ];
  }
}
