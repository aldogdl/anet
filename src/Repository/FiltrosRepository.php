<?php

namespace App\Repository;

use App\Entity\AO1Marcas;
use App\Entity\AO2Modelos;
use App\Entity\Filtros;
use App\Entity\NG1Empresas;
use App\Entity\PiezasName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Filtros|null find($id, $lockMode = null, $lockVersion = null)
 * @method Filtros|null findOneBy(array $criteria, array $orderBy = null)
 * @method Filtros[]    findAll()
 * @method Filtros[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiltrosRepository extends ServiceEntityRepository
{

    private $result = ['abort' => false, 'msg' => '', 'body' => []];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Filtros::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Filtros $entity, bool $flush = true): void
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
    public function remove(Filtros $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /** */
    public function getFiltroByEmp(int $idEmp)
    {
        $dql = 'SELECT f, partial mk.{id}, partial md.{id}, partial pz.{id} FROM ' . Filtros::class . ' f '.
        'LEFT JOIN f.marca mk '.
        'LEFT JOIN f.modelo md '.
        'LEFT JOIN f.pza pz '.
        'WHERE f.emp = :id';
        return $this->_em->createQuery($dql)->setParameter('id', $idEmp);
    }

    /** */
    public function getFiltroById(int $id)
    {
        $dql = 'SELECT f, partial mk.{id}, partial md.{id}, partial pz.{id} FROM ' . Filtros::class . ' f '.
        'LEFT JOIN f.marca mk '.
        'LEFT JOIN f.modelo md '.
        'LEFT JOIN f.pza pz '.
        'WHERE f.id = :id';
        return $this->_em->createQuery($dql)->setParameter('id', $id);
    }

    /** */
    public function delFiltroById(int $id): array
    {
        $this->result['abort'] = true;
        $this->result['body'] = 'No se pudo eliminar el filtro';
        $dql = $this->getFiltroById($id);
        try {
            $objs = $dql->execute();
            if($objs) {
                $this->result['abort'] = false;
                return $this->remove($objs[0]);
            }else{
                $this->result['msg'] = 'No se encontro el Filtro ' .$id;
            }
        } catch (\Throwable $th) {
            $this->result['msg'] = $th->getMessage();
        }
        return $this->result;
    }

    /** */
    public function checkFiltroExiste(array $data): bool
    {
        $params = [];
        $dql = 'SELECT f FROM ' . Filtros::class . ' f ';

        if(array_key_exists('emp', $data)) {
            $dql = $dql . 'WHERE f.emp = :emp ';
            $params['emp'] = $data['emp'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('marca', $data)) {
            $dql = $dql . $pre . ' f.marca = :marca ';
            $params['marca'] = $data['marca'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('modelo', $data)) {
            $dql = $dql . $pre . ' f.modelo = :modelo ';
            $params['modelo'] = $data['modelo'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('anioD', $data)) {
            $dql = $dql . $pre . ' f.anioD = :anioD ';
            $params['anioD'] = $data['anioD'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('anioH', $data)) {
            $dql = $dql . $pre . ' f.anioH = :anioH ';
            $params['anioH'] = $data['anioH'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('pieza', $data)) {
            $dql = $dql . $pre . ' f.pieza = :pieza ';
            $params['pieza'] = $data['pieza'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('pzaName', $data)) {
            $dql = $dql . $pre . ' f.pzaName = :pzaName ';
            $params['pzaName'] = $data['pzaName'];
        }

        $pre = (strpos($dql, 'WHERE') === false) ? ' WHERE ' : ' AND ';
        if(array_key_exists('grupo', $data)) {
            $dql = $dql . $pre . ' f.grupo = :grupo ';
            $params['grupo'] = $data['grupo'];
        }

        $res = $this->_em->createQuery($dql)->setParameters($params)->execute();
        return ($res) ? true : false;
    }

    /** */
    public function setFiltro(array $data): array
    {
        $filtroExiste = $this->checkFiltroExiste($data);
        if($filtroExiste){ return []; }

        $obj = new Filtros();
        $obj->setEmp($this->_em->getPartialReference(NG1Empresas::class, $data['emp']));

        if(array_key_exists('marca', $data)) {
            $obj->setMarca($this->_em->getPartialReference(AO1Marcas::class, $data['marca']));
        }
        if(array_key_exists('modelo', $data)) {
            $obj->setModelo($this->_em->getPartialReference(AO2Modelos::class, $data['modelo']));
        }
        if(array_key_exists('anioD', $data)) {
            $obj->setAnioD($data['anioD']);
        }
        if(array_key_exists('anioH', $data)) {
            $obj->setAnioH($data['anioH']);
        }
        if(array_key_exists('pieza', $data)) {
            $obj->setPieza($data['pieza']);
        }
        if(array_key_exists('pzaName', $data)) {
            $obj->setPza($this->_em->getPartialReference(PiezasName::class, $data['pzaName']));
        }
        if(array_key_exists('grupo', $data)) {
            $obj->setGrupo($data['grupo']);
        }
        $this->add($obj);
        if($this->result['abort']) {
            $this->result['body'] = 'Error al guardar el Filtro';
        }
        return $this->result;
    }
}
