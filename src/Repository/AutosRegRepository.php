<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;
use App\Entity\AutosReg;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AutosReg|null find($id, $lockMode = null, $lockVersion = null)
 * @method AutosReg|null findOneBy(array $criteria, array $orderBy = null)
 * @method AutosReg[]    findAll()
 * @method AutosReg[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AutosRegRepository extends ServiceEntityRepository
{
    
    public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutosReg::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(AutosReg $entity, bool $flush = true): void
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
    public function remove(AutosReg $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function regAuto(array $data): int
    {
        $hasAuto = $this->findOneBy([
            'marca' => $data['id_marca'],
            'modelo'=> $data['id_modelo'],
            'anio'  => $data['anio'],
            'isNac' => $data['is_nacional'],
        ]);

        if($hasAuto) {
            $cant = $hasAuto->getCantReq();
            $cant = $cant + 1;
            $hasAuto->setCantReq($cant);
        }else{
            $hasAuto = new AutosReg();
            $hasAuto->setMarca($this->_em->getPartialReference(AO1Marcas::class, $data['id_marca']));
            $hasAuto->setModelo($this->_em->getPartialReference(AO2Modelos::class, $data['id_modelo']));
            $hasAuto->setAnio($data['anio']);
            $hasAuto->setIsNac($data['is_nacional']);
            $hasAuto->setCantReq(1);
        }
        $this->_em->persist($hasAuto);
        try {
            $this->_em->flush();
            return $hasAuto->getId();
        } catch (\Throwable $th) {
            return 0;
        }
    }


}
