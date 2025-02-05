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
     * Recuperamos todos los registros exepto todos aquellos que coincidan con
     * el slug enviado por paramentro.
     * 
     * @param string $slug El slug del cual no queremos los registros.
     */
    public function getAllBySlugExcept(String $slug): \Doctrine\ORM\Query
    {
        $dql = 'SELECT f FROM '. Fcm::class .' f '.
        'WHERE f.slug != :slug';
        return $this->_em->createQuery($dql)->setParameter('slug', $slug);
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

    /** 
     * Tomamos todos los cotizadores excepto el dueño al remitente enviado
     * por parametro, para enviar las notificaciones.
     * FILTRADO tambien filtramos por excepción de marcas.
    */
    public function getContactsForSend(array $itemPush): array
    {
        // Buscamos contactos para el envio de notificaciones
        $filtros = [];
        if($itemPush['type'] == 'solicita') {
            // Buscar todos los cotizadores diferentes al dueño del ITEM
            $dql = $this->getAllBySlugExcept($itemPush['ownSlug']);
            $contacts = $dql->getResult();
            file_put_contents('push_sent_conta.json', json_encode([
                'cant' => count($contacts),
                'tok'  => $contacts[0]->getTkfcm(),
                'mrnta' => $contacts[0]->getMrnta(),
            ]));

            if(count($contacts) > 0) {

                $noTengoLaMrk = array_filter($contacts, function($contac) {
                    return $contac->getMrnta() == 'd';
                });
                $soloEstasVendo = array_filter($contacts, function($contac) {
                    return $contac->getMrnta() == 'i';
                });
                $contacts = [];

                // Filtramos primero a los especialistas de la marca
                $rota = count($soloEstasVendo);
                for ($i=0; $i < $rota; $i++) { 
                    $filtro = $soloEstasVendo[$i]->getNvm();
                    if($filtro) {
                        if(in_array($itemPush['idMrk'], array_column($filtro, 'idMrk'))) {
                            if(!in_array($soloEstasVendo[$i]->getTkfcm(), $filtros)) {
                                $filtros[] = $soloEstasVendo[$i]->getTkfcm();
                            }
                        }
                    }
                }

                // filtramos a los que no venden la marca
                $rota = count($noTengoLaMrk);
                for ($i=0; $i < $rota; $i++) { 
                    $filtro = $noTengoLaMrk[$i]->getNvm();
                    if($filtro) {
                        if(!in_array($itemPush['idMrk'], array_column($filtro, 'idMrk'))) {
                            if(!in_array($noTengoLaMrk[$i]->getTkfcm(), $filtros)) {
                                $filtros[] = $noTengoLaMrk[$i]->getTkfcm();
                            }
                        }
                    }
                }
            }
        }

        return $filtros;
    }

}
