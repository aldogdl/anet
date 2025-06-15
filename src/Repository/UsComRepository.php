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
    public function add(UsCom $entity, bool $flush = true): UsCom
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
        return $entity;
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
    public function getTokenByWaId(String $waId): String
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId = :waId';

        $res = $this->_em->createQuery($dql)->setParameter('waId', $waId)->execute();
        if($res) {
            $rota = count($res);
            if($rota > 1) {

            }else{
                return $res[0]->getTkfb();
            }
        }
        return '';
    }

    /** 
     * Borramos todos los usuario que coincidan con la lista de Strings de IKU
     * Evitando que un usuario pueda tener varios registros con el mismo WaId
     * desde el mismo dispositivo
    */
    public function deleteAllByIku(array $ikus): void
    {
        $dql = 'DELETE FROM '.UsCom::class.' u WHERE u.iku IN (:ikus)';
        $this->_em->createQuery($dql)->setParameter('ikus', $ikus)->execute();
    }

    /** 
     * Actualizamos solo el token del usuario que coincida con si iku
    */
    public function updateOnlyToken(String $token, String $iku): void
    {
        $dql = 'UPDATE ' . UsCom::class . ' u SET u.tkfb = :token WHERE u.iku = :iku';
        $this->_em->createQuery($dql)->setParameters([
            'iku' => $iku, 'token' => $token
        ])->execute();
    }

    /** 
     * Hacemos una busqueda del registro de una manera mas rapida por SQL nativo
     * en base al waId del usuario y el slug de la empresa
    */
    public function fetchWaId(String $waId, String $slug):  array
    {
        $sql = 'SELECT id, own_app, iku, tkfb FROM us_com WHERE us_wa_id = :waId AND own_app = :slug LIMIT 1';
        
        $conn = $this->_em->getConnection();
        $result = $conn->fetchAssociative($sql, ['waId' => $waId, 'slug' => $slug]);
        if ($result) {
            return $result;
        }
        return [];
    }

    /** 
     * Actualizamos solo el token del usuario que coincida con si iku
    */
    public function fetchByIku(String $iku): ?UsCom
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u WHERE u.iku = :iku';
        $res = $this->_em->createQuery($dql)->setParameter('iku', $iku)->execute();
        if($res) {
            return $res[0];
        }
        return null;
    }

    /** */
    public function updateDataCom(UsCom $obj): array
    {
        $app = $this->fetchByIku($obj->getIku());
        if($app) {
            $app->setStt($obj->getStt());
            $app->setTkfb($obj->getTkfb());
        }else{
            $app = $obj;
        }
        $fechaLimite = (new DateTimeImmutable())->sub(new DateInterval('PT23H55M'));
        if($app->getLastAt() < $fechaLimite) {
            // Han pasado más de 23h 55m desde la fecha
            $app->setStt(0);
        }
        $this->add($app);
        return $app->toJsonResponse();
    }

}
