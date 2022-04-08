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

    
    /**
     * Recuperamos todas las empresas y sus contactos de tipo...
     */
    public function getAllContactsBy(string $tipo): \Doctrine\ORM\Query
    {
        $dql = 'SELECT c, e FROM '. NG2Contactos::class .' c '.
        'JOIN c.empresa e ';

        if($tipo != 'anet') {

            $isCot = ($tipo == 'sol') ? true :  false;
            $dql = $dql.
            'WHERE c.isCot = :isCot AND c.curc NOT LIKE :curc '.
            'ORDER BY c.id ASC';
            return $this->_em->createQuery($dql)->setParameters([
                'isCot'=> $isCot,
                'curc' => 'anet%'
            ]);
        }else{
            $dql = $dql.
            'WHERE c.curc LIKE :curc '.
            'ORDER BY c.id ASC';
            return $this->_em->createQuery($dql)->setParameter('curc', 'anet%');
        }
    }

    /** */
    public function seveDataContact(array $data): array
    {
        $obj = null;
        if($data['id'] != 0) {
            $has = $this->_em->find(NG2Contactos::class, $data['id']);
            if($has) {
                $obj = $has;
                $has = null;
            }
        }

        if(is_null($obj)) {
            $obj = new NG2Contactos();
            $obj->setEmpresa($this->_em->getPartialReference(NG1Empresas::class, $data['empresaId']));
        }
        if(array_key_exists('local', $data)) {
            $obj->setId($data['id']);
        }
        $obj->setCurc('TMP');
        $obj->setPassword($data['password']);
        $obj->setNombre($data['nombre']);
        $obj->setIsCot($data['isCot']);
        $obj->setCargo($data['cargo']);
        $obj->setCelular($data['celular']);
        $obj->setRoles($data['roles']);

        try {
            $this->_em->persist($obj);
            $this->_em->flush();
            $this->buildCredentials($obj);
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
    public function buildCredentials(NG2Contactos $user): void
    {
        if (!$user instanceof NG2Contactos) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $hashed = $this->passwordHasher->hashPassword($user, $user->getPassword());
        $roles = $user->getRoles();
        $isAdmin = true;
        if(!in_array('ROLE_AVO', $roles)) {
            if(!in_array('ROLE_ADMIN', $roles)) {
                if(!in_array('ROLE_SUPER_ADMIN', $roles)) {
                    $isAdmin = false;
                }
            }
        }

        if($isAdmin) {
            $prefix = 'a';
        }else{
            $prefix = (in_array('ROLE_COTZ', $roles)) ? 'c' : 's';
        }

        $curc = $prefix . 'net-e' . $user->getEmpresa()->getId() . 'c' .$user->getId();
        $user->setPassword($hashed);
        $user->setCurc($curc);
        $this->_em->persist($user);
        $this->_em->flush();
        $this->result = [
            'abort' => false, 'msg' => 'ok', 'body' => ['e'=> $user->getEmpresa()->getId(), 'c' => $user->getId(),'curc' => $curc]
        ];
    }

}
