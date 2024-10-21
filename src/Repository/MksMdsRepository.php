<?php

namespace App\Repository;

use App\Entity\MksMds;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MksMds>
 *
 * @method MksMds|null find($id, $lockMode = null, $lockVersion = null)
 * @method MksMds|null findOneBy(array $criteria, array $orderBy = null)
 * @method MksMds[]    findAll()
 * @method MksMds[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MksMdsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MksMds::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(MksMds $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /** */
    public function getMarcaByIdMeli(String $idMeli): \Doctrine\ORM\Query
    {
        $dql = 'SELECT mm FROM ' . MksMds::class . ' mm '.
        'WHERE mm.idMlb = :idMeli';
        return $this->_em->createQuery($dql)->setParameter('idMeli', $idMeli);
    }

    /** */
    public function getAllMarcas(): \Doctrine\ORM\Query
    {
        $dql = 'SELECT mm FROM ' . MksMds::class . ' mm '.
        'WHERE mm.idMrk = :idMrk';
        return $this->_em->createQuery($dql)->setParameter('idMrk', 0);
    }

    /** */
    public function buildFileMarcasAndModelos(): void
    {
        $dql = 'SELECT mm FROM ' . MksMds::class . ' mm ';
        $mm = $this->_em->createQuery($dql)->getArrayResult();
        file_put_contents('scm/brands_anet.json', json_encode($mm));
    }

    /** 
     * Creamos la lista de marcas y modelos a partir del archivo json
    */
    public function createMarcasFromFile(): void
    {
        $hasFlush = false;
        $mms = json_decode(file_get_contents('scm/brands_anet_builder.json'), true);
        $rota = count($mms);
        for ($i=0; $i < $rota; $i++) {
            if(!array_key_exists('idMrk', $mms[$i])) {
                $item = new MksMds();
                // Agregamos una marca
                $item->fromFileMrk($mms[$i]);
                $this->add($item, false);
                $hasFlush = true;
            }
        }

        if($hasFlush) {
            $this->_em->flush();
        }
    }

    /** 
     * Creamos la lista de marcas y modelos a partir del archivo json
    */
    public function createModelosFromFile(): void
    {
        $marcasDql = $this->getAllMarcas();
        $marcas = $marcasDql->getArrayResult();
        $vueltas = count($marcas);
        if($vueltas == 0) {
            return;
        }

        $mms = json_decode(file_get_contents('scm/brands_anet_builder.json'), true);
        $idsMeli = array_column($marcas, 'idMlb');
        $idsMrks = array_column($mms, 'id');

        $mrk = [];
        $hasFlush = false;
        $rota = count($mms);
        for ($i=0; $i < $rota; $i++) { 

            if(array_key_exists('idMrk', $mms[$i])) {

                // Se trata de un modelo, por lo tanto buscamos la marca correspondiente
                $item = new MksMds();
                $index = array_search($mms[$i]['idMrk'], $idsMrks);
                if($index !== false) {
                    $index = array_search($mms[$index]['idMl'], $idsMeli);
                    $mrk = ($index !== false) ? $marcas[$index] : [];
                }
                if(count($mrk) > 0) {
                    $item->fromFileMdl($mrk['id'], $mms[$i]);
                    $this->add($item, false);
                    $hasFlush = true;
                }
            }
        }

        if($hasFlush) {
            $this->_em->flush();
        }
    }

}
