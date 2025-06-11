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
    
    /** 
     * Recuperamos a los usuarios que coincidan con el waId y el
     * tipo de dispositivo al cual estan conectador
     */
    public function getByWaIdAndDev(String $waId, String $dev): array
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId = :waId AND u.dev = :dev '.
        'ORDER BY u.lastAt DESC';

        $res = $this->_em->createQuery($dql)->setParameters(['waId' => $waId, 'dev' => $dev])->execute();
        if ($res) {
            return $res;
        }
        return [];
    }
    
    /** 
     * Recuperamos a un usuario segun su id, dispositivo y el slug del yonkero dueño de la app
     */
    public function getByWaIdDevAndOwnApp(String $waId, String $dev, String $ownApp): array
    {
        $dql = 'SELECT u FROM ' . UsCom::class . ' u '.
        'WHERE u.usWaId = :waId AND u.dev = :dev AND u.ownApp = :ownApp '.
        'ORDER BY u.lastAt DESC ';

        $res = $this->_em->createQuery($dql)->setParameters([
            'waId' => $waId, 'dev' => $dev, 'ownApp' => $ownApp
        ])->execute();

        if ($res) {
            return $res;
        }
        return [];
    }
    
    /** 
     * Recuperamos los datos de contacto de todos los colaboradores de un Yonek
     */
    public function getDataComColabs(String $ownApp, array $waIds): array
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
            // Creamos un map para evitar duplicidad
            if(!array_key_exists($data[$i]['usWaId'], $results)) {
                $results[$data[$i]['usWaId']] = [
                    "iku"    => $data[$i]['iku'],
                    "tk"     => $data[$i]['tkfb'],
                    "stt"    => $data[$i]['stt'],
                    "dev"    => $data[$i]['dev'],
                    "role"   => $data[$i]['role'],
                    "usWaId" => $data[$i]['usWaId'],
                    "usName" => $data[$i]['usName'],
                    "usPlace"=> $data[$i]['usPlace'],
                    "usEmail" => $data[$i]['usEmail'],
                    "lastAt" => $data[$i]['lastAt'],
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

    /** */
    public function updateDataCom(UsCom $obj): ?UsCom
    {
        $updateReg = false;
        if($obj->getRole() == 'b') {
            $users = $this->getByWaIdAndDev($obj->getUsWaId(), $obj->getDev());            
        }else{
            $users = $this->getByWaIdDevAndOwnApp($obj->getUsWaId(), $obj->getDev(), $obj->getOwnApp());
        }

        $rota = count($users);
        if($rota == 0) {
            $has = $obj;
        } elseif ($rota == 1) {
            $has = $users[0];
            $updateReg = true;
        }else{
            $has = $users[0];
            $ikus = [];
            for ($i=0; $i < $rota; $i++) { 
                if($i > 0) {
                    $ikus[] = $users[$i]->getIku(); 
                }
            }
            if(count($ikus) > 0) {
                $this->deleteAllByIku($ikus);
            }
            $updateReg = true;
        }

        if($updateReg) {
            $has->setTkfb($obj->getTkfb());
            $has->setStt($obj->getStt());
        }
        
        $fechaLimite = (new DateTimeImmutable())->sub(new DateInterval('PT23H55M'));
        if($has->getLastAt() < $fechaLimite) {
            // Han pasado más de 23h 55m desde la fecha
            $has = $has->setStt(0);
        }
        return $this->add($has);
    }

}
