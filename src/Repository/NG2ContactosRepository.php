<?php

namespace App\Repository;

use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

use App\Entity\NG2Contactos;

/**
 * @method NG2Contactos|null find($id, $lockMode = null, $lockVersion = null)
 * @method NG2Contactos|null findOneBy(array $criteria, array $orderBy = null)
 * @method NG2Contactos[]    findAll()
 * @method NG2Contactos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NG2ContactosRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NG2Contactos::class);
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
}
