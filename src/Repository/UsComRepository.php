<?php

namespace App\Repository;

use DateTimeImmutable;
use DateInterval;

use App\Entity\UsCom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsCom>
 *
 * @method UsCom|null find($id, $lockMode = null, $lockVersion = null)
 * @method UsCom|null findOneBy(array $criteria, array $orderBy = null)
 * @method UsCom[]    findAll()
 * @method UsCom[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsComRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsCom::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(UsCom $entity, bool $flush = true): void
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
    public function remove(UsCom $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }
    
    /** Recuperamos a un usuario segun su id y su dev */
    public function getUserByWaId(String $waId, String $dev): ?UsCom
    {
        $dql = 'SELECT u FROM ' . UsCom::class . 'u'.
        'WHERE u.usWaId = :waId AND u.dev = :dev';

        $res = $this->_em->createQuery($dql)->setParameters(['waId' => $waId, 'dev' => $dev]);
        if ($res) {
            return $res[0];
        }
        return null;
    }

    /** */
    public function updateTkFb(UsCom $obj): void {

        $has = $this->getUserByWaId($obj->getUsWaId(), $obj->getDev());
        if($has) {
            $has->setTkfb($obj->getTkfb());
        }else{
            
        }

        $fechaLimite = (new DateTimeImmutable())->sub(new DateInterval('PT23H55M'));
        if($has->getLastAt() < $fechaLimite) {
            // Han pasado más de 23h55m desde la fecha
            $has = $has->setStt(0);
        }
    }
}
