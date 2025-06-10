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
    public function getUserByWaIdAndDev(String $waId, String $dev): ?UsCom
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId = :waId AND u.dev = :dev';

        $res = $this->_em->createQuery($dql)->setParameters(['waId' => $waId, 'dev' => $dev])->execute();
        if ($res) {
            return $res[0];
        }
        return null;
    }
    
    /** 
     * Recuperamos a un usuario segun su id, dispositivo y el slug del yonkero dueño de la app
     */
    public function getUserByWaIdDevAndOwnApp(String $waId, String $dev, String $ownApp): ?UsCom
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId = :waId AND u.dev = :dev AND u.ownApp = :ownApp';

        $res = $this->_em->createQuery($dql)->setParameters([
            'waId' => $waId, 'dev' => $dev, 'ownApp' => $ownApp
        ])->execute();

        if ($res) {
            return $res[0];
        }
        return null;
    }
    
    /** 
     * Recuperamos los datos de contacto de todos los colaboradores de un Yonek
     */
    public function getDataComColabs(String $ownApp, array $waIds, String $dev): array
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId IN(:waIds) AND u.ownApp = :ownApp '.
        'ORDER BY u.lastAt DESC ';

        $data = $this->_em->createQuery($dql)->setParameters([
            'waIds' => $waIds, 'ownApp' => $ownApp
        ])->getArrayResult();

        $results = [];
        $rota = count($data);
        for ($i=0; $i < $rota; $i++) {

            if(!array_key_exists($data[$i]['usWaId'], $results)) {
                $results[$data[$i]['usWaId']] = [
                    "iku"    => $data[$i]['iku'],
                    "tk"     => $data[$i]['tkfb'],
                    "stt"    => $data[$i]['stt'],
                    "dev"    => $data[$i]['dev'],
                    "role"   => $data[$i]['role'],
                    "usWaId" => $data[$i]['usWaId'],
                    "usName" => $data[$i]['usName'],
                    "lastAt" => $data[$i]['lastAt'],
                    "usPlace"=> $data[$i]['usPlace'],
                ];
            }
        }

        return $results;
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

    /** */
    public function updateTkFb(UsCom $obj): array
    {
        $has = $this->getUserByWaIdAndDev($obj->getUsWaId(), $obj->getDev());
        if($has) {
            $has->setTkfb($obj->getTkfb());
        }else{
            $has = $obj;
        }

        $fechaLimite = (new DateTimeImmutable())->sub(new DateInterval('PT23H55M'));
        if($has->getLastAt() < $fechaLimite) {
            // Han pasado más de 23h 55m desde la fecha
            $has = $has->setStt(0);
        }
        $has = $this->add($has);
        return ['id' => $has->getId(), 'stt' => $has->getStt()];
    }

    /** 
     * Desde el frm de anyShop, guardamos los datos del usuario
    */
    public function setUserFromForm(array $data): int
    {
        $obj = $this->getUserByWaIdDevAndOwnApp($data['w'], $data['pl'], $data['s']);
        if($obj) {

        }
        return 0;
    }

}
