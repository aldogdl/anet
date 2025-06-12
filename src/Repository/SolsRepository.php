<?php

namespace App\Repository;

use App\Entity\Sols;
use App\Entity\UsCom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sols>
 *
 * @method Sols|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sols|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sols[]    findAll()
 * @method Sols[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SolsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sols::class);
    }

    /** */
    public function setSol(array $solNew) : int {
        
        $usCom = $this->_em->getRepository(UsCom::class)
            ->findBy(['iku' => $solNew['iku'], 'usWaId' => $solNew['wi']]);
        if (!$usCom) {
            throw new \Exception('UsCom no encontrado');
        }
        $data = $solNew['sol'];
        // Crear nueva solicitud
        $sol = new Sols();
        $sol->setCode($data['code']);
        if(array_key_exists('detalle', $data)) {
            $sol->setDetalle($data['detalle']);
        }
        $sol->setIku($usCom[0]);
        $sol->setAppSlug($solNew['sl']);
        $sol->setAppWaId($solNew['owi']);

        // Guardar en la base de datos
        try {
            $this->_em->persist($sol);
            $this->_em->flush();
            return $sol->getId();
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /** */
    public function getMiSolicitudes(String $appSlug, String $usIku): array
    {
        $dql = 'SELECT * ';
        return [];
    }
}
