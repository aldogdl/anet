<?php

namespace App\Repository;

use App\Entity\NG1Empresas;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use App\Entity\NG2Contactos;

/**
 * @method NG2Contactos|null find($id, $lockMode = null, $lockVersion = null)
 * @method NG2Contactos|null findOneBy(array $criteria, array $orderBy = null)
 * @method NG2Contactos[]    findAll()
 * @method NG2Contactos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NG2ContactosRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{

  public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];
  public $passwordHasher;

  public function __construct(ManagerRegistry $registry, UserPasswordHasherInterface $passwordHasher)
  {
      parent::__construct($registry, NG2Contactos::class);
      $this->passwordHasher = $passwordHasher;
  }

  /**
   * @throws ORMException
   * @throws OptimisticLockException
   */
  public function add(NG2Contactos $entity, bool $flush = true): void
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
  public function remove(NG2Contactos $entity, bool $flush = true): void
  {
      $this->_em->remove($entity);
      if ($flush) {
          $this->_em->flush();
      }
  }

  ///
  public function toArray(NG2Contactos $entity): array
  {
    return [
      'id' => $entity->getId(),
      'nombre' => $entity->getNombre(),
      'curc' => $entity->getCurc(),
      'roles' => $entity->getRoles(),
    ];
  }

  /**
   * Used to upgrade (rehash) the user's password automatically over time.
   */
  public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
  {
    if (!$user instanceof NG2Contactos) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
    }
    
    $user->setPassword($newHashedPassword);
    $this->_em->persist($user);
    $this->_em->flush();
  }

  /**
   *
   */
  public function safeTokenMessangings(array $data): void
  {
    $user = $this->_em->find(NG2Contactos::class, $data['user']);
    if($user) {
      if($data['toSafe'] == 'web') {
          $user->setKeyWeb($data['token']);
      }else{
          $user->setKeyCel($data['token']);
      }
      $this->_em->persist($user);
      $this->_em->flush();
    }
  }

  public function getTokensByIds(array $ids): array
  {
    $tokens = [];
    $dql = 'SELECT partial c.{id, keyCel} FROM ' . NG2Contactos::class . ' c '.
    'WHERE c.id IN (:ids)';
    return $this->_em->createQuery($dql)->setParameter('ids', $ids)->getArrayResult();
  }

  /**
   * Recuperamos todas las empresas y sus contactos que son cotizadores
   */
  public function getAllCotizadores(bool $isMini = false): \Doctrine\ORM\Query
  {   
    if($isMini) {
      $dql = 'SELECT partial c.{id, curc, nombre, cargo, celular}, partial e.{id, nombre, isLocal}, ';
    }else{
      $dql = 'SELECT partial c.{id, curc, roles, nombre, isCot, cargo, celular}, e, ';
    }
    $dql = $dql . 'f, partial mk.{id, logo, grupo}, partial md.{id}, partial pz.{id} ';
    $dql = $dql . 'FROM '. NG2Contactos::class . ' c '.
    'JOIN c.empresa e '.
    'LEFT JOIN e.filtros f WITH f.emp = e.id '.
    'LEFT JOIN f.marca mk '.
    'LEFT JOIN f.modelo md '.
    'LEFT JOIN f.pza pz '.
    'WHERE c.isCot = :verdadero '.
    'ORDER BY e.nombre ASC';
    return $this->_em->createQuery($dql)->setParameter('verdadero', true);
  }

  /**
   * Recuperamos la empresas y su contactos por medio de su ID, es generalmente
   * para cuando se esta creando una campaña y este dato no esta en el archivo local
   */
  public function getContactById(int $id, bool $isAvo = false): \Doctrine\ORM\Query
  { 
    $dql = 'SELECT partial c.{id, curc, nombre, cargo, celular, roles} ';
    if(!$isAvo) {
      $dql = $dql . ', partial e.{id, nombre, isLocal} ';
    }
    $dql = $dql . 'FROM '. NG2Contactos::class . ' c ';
    if(!$isAvo) {
      $dql = $dql . 'JOIN c.empresa e ';
    }
    $dql = $dql . 'WHERE c.id = :id ';
    return $this->_em->createQuery($dql)->setParameter('id', $id);
  }

  /**
   * Recuperamos el contacto por su ID
   */
  public function getContactoById(int $idContac): \Doctrine\ORM\Query
  {
    $dql = 'SELECT partial c.{id, curc, roles, nombre, isCot, cargo, celular}, e FROM '. NG2Contactos::class .' c '.
    'JOIN c.empresa e '.
    'WHERE c.id = :idC '.
    'ORDER BY e.nombre ASC';
    return $this->_em->createQuery($dql)->setParameter('idC', $idContac);
  }

  /**
   * Recuperamos el id de la empresa que pertenece el contacto
   */
  public function getIdEmpresaByIdContacto(int $idContac): int
  {
    $dql = 'SELECT partial c.{id}, partial e.{id} FROM '. NG2Contactos::class .' c '.
    'JOIN c.empresa e '.
    'WHERE c.id = :idC';
    $res = $this->_em->createQuery($dql)->setParameter('idC', $idContac)->execute();
    if($res) {
      return $res[0]->getEmpresa()->getId();
    }
    return 0;
  }

  /**
   * Recuperamos todas las empresas y sus contactos de tipo...
   */
  public function getAllContactsBy(string $tipo): \Doctrine\ORM\Query
  {
    $neg = ($tipo == 'anete') ? '' : 'NOT';
    $dql = 'SELECT partial c.{id, curc, roles, nombre, isCot, cargo, celular}, e FROM '. NG2Contactos::class .' c '.
    'JOIN c.empresa e '.
    'WHERE c.curc ' .$neg. ' LIKE :curc '.
    'ORDER BY c.id ASC';
    return $this->_em->createQuery($dql)->setParameter('curc', $tipo.'%');
  }

  /** */
  public function borrarContactoById(int $idcontact): array
  {
    $ct = $this->_em->find(NG2Contactos::class, $idcontact);

    if($ct) {
      $emp = $this->_em->find(NG1Empresas::class, $ct->getEmpresa()->getId());

      if($emp) {
        try {
          if($emp->getId() != 1) {
            $this->_em->remove($emp);
          }
          $this->_em->remove($ct);
          $this->_em->flush();
        } catch (\Throwable $th) {
          $this->result = [
            'abort' => true,
            'msg'   => $th->getMessage(),
            'body'  => 'Error al borrar contacto, inténtalo nuevamente'
          ];
        }
      }else{
        $this->result = [
          'abort' => true,
          'msg'   => 'err',
          'body'  => 'No se encontró la empresa con el ID ' . $ct->getEmpresa()->getId()
        ];
      }
    }else{
      $this->result = [
        'abort' => true,
        'msg'   => 'err',
        'body'  => 'No se encontró el contacto con el ID ' . $idcontact
      ];
    }

    return $this->result;
  }

  /** */
  public function seveDataContact(array $data): array
  {
    $obj = null;
    $isEditing = false;
    $buildCurc = true;
    if($data['id'] != 0) {
      $has = $this->_em->find(NG2Contactos::class, $data['id']);
      if($has) {
        $obj = $has;
        $isEditing = true;
        $has = null;
      }
    }
    $password = $data['password'];
    if(is_null($obj)) {
      $obj = new NG2Contactos();
      $obj->setEmpresa($this->_em->getPartialReference(NG1Empresas::class, $data['empresaId']));
      $password = $this->encodePassword($obj, $password);
    }else{

      if($isEditing) {
        if($data['password'] != 'same-password'){
          $password = $this->encodePassword($obj, $password);
        }else{
          $password =  $obj->getPassword();
        }
      }else{
        $password =  $obj->getPassword();
      }
    }

    // Colocamos TEMPORALMENTE el celular ya que es un campo que no se repite.
    $obj->setCurc($data['celular']);
    if(array_key_exists('local', $data)) {
      $obj->setCurc($data['curc']);
      $buildCurc = false;
    }else{
      if($isEditing) {
        $obj = $this->buildCurc($obj, false);
        $buildCurc = false;
      }
    }
    
    $obj->setPassword($password);
    $obj->setNombre($data['nombre']);
    $obj->setIsCot($data['isCot']);
    $obj->setCargo($data['cargo']);
    $obj->setCelular($data['celular']);
    $obj->setRoles($data['roles']);

    try {
        $this->add($obj);
        if($buildCurc) {
          $obj = $this->buildCurc($obj);
        }
        if(array_key_exists('local', $data)) {
          $obj = $this->revisarIdTable($obj, $data);
        }
        $this->result = [
          'abort'=> false,
          'msg'  => 'ok',
          'body' => ['e'=> $obj->getEmpresa()->getId(), 'c' => $obj->getId(), 'curc' => $obj->getCurc()]
        ];
    } catch (\Throwable $th) {
        $this->result['abort'] = true;
        $this->result['msg'] = $th->getMessage();
        $this->result['body'] = 'No se guardo el contacto';
    }
    return $this->result;
  }

  /**
   * Construimos las credenciales password y curc
   */
  public function encodePassword(PasswordAuthenticatedUserInterface $user, $pass): String
  {
    if (!$user instanceof NG2Contactos) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
    }

    return $this->passwordHasher->hashPassword($user, $pass);
  }

  /**
   * Construimos las credenciales password y curc
   */
  public function buildCurc(NG2Contactos $user, bool $flush = true): NG2Contactos
  {
    if (!$user instanceof NG2Contactos) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
    }

    $roles = $user->getRoles();
    $isAdmin = true;
    if(!in_array('ROLE_AVO', $roles)) {
      if(!in_array('ROLE_ADMIN', $roles)) {
        if(!in_array('ROLE_SUPER_ADMIN', $roles)) {
          if(!in_array('ROLE_EVAL', $roles)) {
            $isAdmin = false;
          }
        }
      }
    }

    $prefix = 'anet';
    if($isAdmin) {
      $sufix = 'e';
    }else{
      $sufix = (in_array('ROLE_COTZ', $roles)) ? 'c' : 's';
    }

    $curc = $prefix . $sufix .  $user->getEmpresa()->getId() . 'c' .$user->getId();
    $user->setCurc($curc);
    if($flush) {
      $this->add($user);
    }
    return $user;
  }

  ///
  private function revisarIdTable(NG2Contactos $ctc, array $dataSend): NG2Contactos
  {
    if(array_key_exists('id', $dataSend)) {
      if($ctc->getId() != $dataSend['id']) {
        $dql = 'UPDATE ' . NG2Contactos::class . ' c '.
        'SET c.id = :idN '.
        'WHERE c.id = :id';
        $this->_em->createQuery($dql)->setParameters([
          'idN' => $dataSend['id'], 'id' => $ctc->getId()
        ])->execute();
      }
    }
    return $this->_em->find(NG2Contactos::class, $dataSend['id']);
  }
}
