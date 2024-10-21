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

    /** 
     * Creamos la lista de marcas y modelos a partir del archivo json
    */
    public function createMarcasFromFile(): void
    {
        $mms = json_decode(file_get_contents('scm/brands_anet.json'), true);
        $rota = count($mms);
        var_dump($rota);
        $hasFlush = false;
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

        $mms = json_decode(file_get_contents('scm/brands_anet.json'), true);
        $idsMeli = array_column($marcas, 'idMlb');
        $idsMrks = array_column($mms, 'idMrk');
        sort($idsMeli);
        sort($idsMrks);

        $mrk = [];
        $hasFlush = false;
        $rota = count($mms);
        for ($i=0; $i < $rota; $i++) { 

            if(array_key_exists('idMrk', $mms[$i])) {
                $item = new MksMds();
                // Se trata de un modelo, por lo tanto buscamos la marca correspondiente
                $buscar = true;
                if(count($mrk) > 0) {
                    if($mrk['idMlb'] == $mms[$i]['idMl']) {
                        $buscar = false;
                    }
                }
                if($buscar) {

                    $index = array_search($mms[$i]['idMl'], $idsMeli);
                    dd($index, $mms[$i]['idMl'], $idsMeli);
                    $mrk = ($index !== false) ? $marcas[$index] : [];
                }
                if($mrk) {
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
