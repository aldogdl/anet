<?php

namespace App\Repository;

use App\Entity\PiezasName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PiezasName|null find($id, $lockMode = null, $lockVersion = null)
 * @method PiezasName|null findOneBy(array $criteria, array $orderBy = null)
 * @method PiezasName[]    findAll()
 * @method PiezasName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PiezasNameRepository extends ServiceEntityRepository
{

    private $result = ['abort' => false, 'msg' => 'ok', 'body' => []];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PiezasName::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(PiezasName $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            try {
                $this->_em->flush();
                $this->result['body'] = $entity->getId();
            } catch (\Throwable $th) {
                $this->result['abort'] = true;
                $this->result['msg'] = $th->getMessage();
            }
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(PiezasName $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            try {
                $this->_em->flush();
            } catch (\Throwable $th) {
                $this->result['abort'] = true;
                $this->result['msg'] = $th->getMessage();
            }
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function getPiezaById(int $id): \Doctrine\ORM\Query
    {
        $dql = 'SELECT p FROM ' . PiezasName::class . ' p '.
        'WHERE p.id = :id';
        return $this->_em->createQuery($dql)->setParameter('id', $id);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function setPieza(array $pieza): array
    {
        $obj = new PiezasName();
        if(!array_key_exists('stt', $pieza)) {
            $dql = $this->getPiezaById($pieza['id']);
            $tmp = $dql->execute();
            if($tmp) {
                $obj = $tmp[0];
            }
        }
        $obj->setNombre($pieza['value']);
        $obj->setSimyls($pieza['simyls']);
        $this->add($obj);
        if($this->result['abort']) {
            $this->result['body'] = 'No se pudo guardar la pieza';
        }
        return $this->result;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delPieza(array $pieza): array
    {
        $dql = $this->getPiezaById($pieza['id']);
        $tmp = $dql->execute();
        if($tmp) {
            $obj = $tmp[0];
            $this->remove($obj);
            if($this->result['abort']) {
                $this->result['body'] = 'No se pudo borrar la pieza';
            }
        }

        return $this->result;
    }


}
