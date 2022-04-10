<?php

namespace App\Repository;

use App\Entity\NG1Empresas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NG1Empresas|null find($id, $lockMode = null, $lockVersion = null)
 * @method NG1Empresas|null findOneBy(array $criteria, array $orderBy = null)
 * @method NG1Empresas[]    findAll()
 * @method NG1Empresas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NG1EmpresasRepository extends ServiceEntityRepository
{

    public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NG1Empresas::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(NG1Empresas $entity, bool $flush = true): void
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
    public function remove(NG1Empresas $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /** */
    public function getEmpresaById(int $id): \Doctrine\ORM\Query
    {
        $dql = 'SELECT e FROM ' . NG1Empresas::class . ' e '.
        'WHERE e.id = :id';
        return $this->_em->createQuery($dql);
    }

    /** */
    public function seveDataContact(array $data): array
    {
        $obj = null;
        if($data['id'] != 0) {
            $has = $this->_em->find(NG1Empresas::class, $data['id']);
            if($has) {
                $obj = $has;
                $has = null;
            }
        }
        if(is_null($obj)) {
            $obj = new NG1Empresas();
        }

        $obj->setNombre($data['nombre']);
        $obj->setDomicilio($data['domicilio']);
        $obj->setCp($data['cp']);
        $obj->setIsLocal($data['isLocal']);
        $obj->setTelFijo($data['telFijo']);
        $obj->setLatLng($data['latLng']);
        try {
            $this->add($obj, true);
            if(array_key_exists('local', $data)) {
                $obj = $this->revisarIdTable($obj, $data);
            }
            $this->result['body'] = $obj->getId();
        } catch (\Throwable $th) {
            $this->result['abort'] = true;
            $this->result['body'] = 'No se guardo la empresa';
        }
        return $this->result;
    }

    ///
    private function revisarIdTable(NG1Empresas $emp, array $dataSend): NG1Empresas
    {
        if(array_key_exists('id', $dataSend)) {
            if($emp->getId() != $dataSend['id']) {
                $dql = 'UPDATE ' . NG1Empresas::class . ' e '.
                'SET e.id = :idN '.
                'WHERE e.id = :id';
                $this->_em->createQuery($dql)->setParameters([
                    'idN' => $dataSend['id'], 'id' => $emp->getId()
                ])->execute();
            }
        }
        return $this->_em->find(NG1Empresas::class, $dataSend['id']);
        // $connDb = $this->getEntityManager()->getConnection();
        // $connDb->prepare('ALTER TABLE my_table AUTO_INCREMENT = 100;')->executeStatement();
    }
}
