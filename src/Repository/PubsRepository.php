<?php

namespace App\Repository;

use App\Entity\Pubs;
use App\Entity\UsCom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pubs>
 *
 * @method Pubs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pubs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pubs[]    findAll()
 * @method Pubs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PubsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pubs::class);
    }

    /** */
    public function buildPakegeOf(String $slug): array
    {
        $dql = 'SELECT u FROM ' . Pubs::class . ' u WHERE u.appSlug = :slug '.
        'ORDER BY u.id ASC';

        $query = $this->_em->createQuery($dql)->setParameter('slug', $slug)->setMaxResults(200);
        $res = $query->getResult();

        $rota = count($res);
        $results = [];
        for ($i=0; $i < $rota; $i++) { 
            $results[] = $res[$i]->buildToSave();
        }
        return $results;
    }

    /** */
    public function setPubs(array $pubNew) : array
    {
        $results = [];
        $rota = $pubNew['list'];
        for ($i=0; $i < $rota; $i++) { 
            $pub = $this->hidratarPub($pubNew['pubs'][$i]);
            $pub->setAppSlug($pubNew['sl']);
            $pub->setAppWaId($pubNew['wi']);
            $pub->setIkuOwn($pubNew['iku']);
            $this->_em->persist($pub);
            $results[] = $pubNew['pubs'][$i]['idSrc'];
        }
        
        // Guardar en la base de datos
        try {
            $this->_em->flush();
        } catch (\Throwable $th) {
            return [$th->getMessage()];
        }

        return $results;
    }

    /** */
    public function setPub(array $pubNew) : int
    {
        // Crear nueva publicacion
        $pub = $this->hidratarPub($pubNew['pub']);
        $pub->setAppSlug($pubNew['sl']);
        $pub->setAppWaId($pubNew['wi']);
        $pub->setIkuOwn($pubNew['iku']);
        
        // Guardar en la base de datos
        try {
            $this->_em->persist($pub);
            $this->_em->flush();
            return $pub->getId();
        } catch (\Throwable $th) {}
        return 0;
    }

    private function hidratarPub(array $data) : Pubs {
        
        // Crear nueva publicacion
        $pub = new Pubs();
        $pub->setIku($data['iku']);

        $pub->setCode($data['code']);
        if(array_key_exists('detalle', $data)) {
            $pub->setDetalle($data['detalle']);
        }
        $pub->setFtoRef($data['ftoRef']);
        if(array_key_exists('fotos', $data)) {
            $pub->setFotos($data['fotos']);
        }
        $pub->setPrice($data['price']);
        if(array_key_exists('costo', $data)) {
            $pub->setCosto($data['costo']);
        }
        if(array_key_exists('ftec', $data)) {
            $pub->setFtec($data['ftec']);
        }
        return $pub;
    }
}
