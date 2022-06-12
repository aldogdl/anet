<?php

namespace App\Repository;

use App\Entity\ScmReceivers;
use App\Entity\ScmCamp;
use App\Entity\NG2Contactos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScmReceivers|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScmReceivers|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScmReceivers[]    findAll()
 * @method ScmReceivers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScmReceiversRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScmReceivers::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(ScmReceivers $entity, bool $flush = true): void
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
    public function remove(ScmReceivers $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /***/
    public function setRegMsgSended(array $data)
    {
      $obj = new ScmReceivers();
      $obj->setCamp($this->_em->getPartialReference(ScmCamp::class, $data['camp']));
      $obj->setReceiver($this->_em->getPartialReference(NG2Contactos::class, $data['receiver']));
      $obj->setStt($data['stt']);
      $this->add($obj, true);
    }
}
