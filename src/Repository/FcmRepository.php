<?php

namespace App\Repository;

use App\Entity\Fcm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fcm>
 *
 * @method Fcm|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fcm|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fcm[]    findAll()
 * @method Fcm[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FcmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fcm::class);
    }

    /**
     * Este método recupera la entidad Fcm basada en el ID de WhatsApp (waId) proporcionado.
     * 
     * @param string $waId El ID de WhatsApp para buscar.
     * @return Fcm|null La entidad Fcm si se encuentra, o null si no se encuentra.
     */
    public function getTokenByWaIdAndDevice(String $waId, String $device): ?Fcm
    {
        $dql = 'SELECT f FROM '. Fcm::class .' f '.
        'WHERE f.waId = :waId AND f.device = :device';
        
        return $this->_em->createQuery($dql)
            ->setParameters(['waId' => $waId, 'device' => $device])->getOneOrNullResult();
    }

    /**
     * Este método establece el token de datos basado en la información
     * proporcionada.
     * 
     * @param array $data Un array asociativo que contiene 'waId' y 'device'.
     * @return String
     */
    public function setDataToken(array $data): String
    {
        $result = 'X Error inesperado';
        $save = false;
        $obj = $this->getTokenByWaIdAndDevice($data['waId'], $data['device']);
        if($obj != null) {
            if($obj->getTkfcm() != $data['token']) {
                $obj->setTkfcm($data['token']);
                $result = 'Actualizado con éxito';
                $save = true;
            }else{
                $result = 'Encontrado y Sin Acciones';
            }
        }else{
            $clase = new Fcm();
            $obj = $clase->fromJson($data);
            $result = 'Guardado con éxito';
            $save = true;
        }
        if($obj && $save) {
            try {
                $this->_em->persist($obj);
                $this->_em->flush();
            } catch (\Throwable $th) {
                $result = 'X '.$th->getMessage();
            }
        }
        return $result;
    }
}
