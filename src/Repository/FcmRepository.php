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
     * Este método recupera todos los miembros de una empresa por medio del 
     * waId del solicitante.
     * 
     * @param string $waId El ID de WhatsApp para buscar.
     * @return \Doctrine\ORM\Query
     */
    public function getAllMiembrosByWaId(String $waId): \Doctrine\ORM\Query
    {
        $dql = 'SELECT f FROM '. Fcm::class .' f '.
        'WHERE f.slug IN (SELECT f2.slug FROM '. Fcm::class .' f2 WHERE f2.waId = :waId)';

        return $this->_em->createQuery($dql)->setParameter('waId', $waId);
    }

    /**
     * Recuperamos todos los registros exepto todos aquellos que coincidan con
     * el slug que halla coincidido con el waId.
     * 
     * @param string $waId El waId del cual no queremos los registros.
     */
    public function getAllByWaIdExcept(String $waId): \Doctrine\ORM\Query
    {
        $dql = 'SELECT f FROM '. Fcm::class .' f '.
        'WHERE f.slug NOT IN (SELECT f2.slug FROM '. Fcm::class .' f2 WHERE f2.waId = :waId)';

        return $this->_em->createQuery($dql)->setParameter('waId', $waId);
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
     * Este método guarda los registros de no vendo la marca
     * 
     * @param array $data Un array asociativo que contiene los datos de la
     * marca que no se vende.
     * @return String
     */
    public function setDataNTGA(array $data): void
    {
        if(array_key_exists('idDbSr', $data)) {
            unset($data['idDbSr']);
        }
        $filtros[] = $data;

        $dql = $this->getAllMiembrosByWaId($data['waId']);
        $miem = $dql->getResult();
        $rota = count($miem);
        for ($i=0; $i < $rota; $i++) { 
            
            // Sincronizamos los datos
            $nvm = $miem[$i]->getNvM();
            $vueltas = count($nvm);
            for ($m=0; $m < $vueltas; $m++) {
                $currents = array_search($nvm[$m]['idMrk'], array_column($filtros, 'idMrk'));
                if($currents === false) {
                    $filtros[] = $nvm[$m];
                }
            }
        }

        for ($i=0; $i < $rota; $i++) { 
            $miem[$i]->setNvM($filtros);
            $this->_em->persist($miem[$i]);
        }
        $this->_em->flush();
        return;
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
        $waIds = [];
        if($itemPush['type'] == 'solicita') {

            // Buscar todos los cotizadores diferentes al dueño del ITEM
            $dql = $this->getAllByWaIdExcept($itemPush['ownWaId']);
            $contacts = $dql->getResult();
            
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
                                $waIds[] = $soloEstasVendo[$i]->getWaid();
                            }
                        }
                    }
                }

                // filtramos a los que no venden la marca
                $rota = count($noTengoLaMrk);
                for ($i=0; $i < $rota; $i++) { 
                    $add = false;
                    $filtro = $noTengoLaMrk[$i]->getNvm();
                    if($filtro) {
                        if(!in_array($itemPush['idMrk'], array_column($filtro, 'idMrk'))) {
                            $add = true;
                        }
                    }else{
                        $add = true;
                    }
                    if($add) {
                        if(!in_array($noTengoLaMrk[$i]->getTkfcm(), $filtros)) {
                            $filtros[] = $noTengoLaMrk[$i]->getTkfcm();
                            $waIds[] = $noTengoLaMrk[$i]->getWaid();
                        }
                    }
                }
            }

        } else if($itemPush['type'] == 'publica') {

            if(!array_key_exists('srcWaId', $itemPush)) {
                return [];
            }

            // Buscar al cotizador quien realizó la solictud de cotizacion
            $dql = $this->getAllMiembrosByWaId($itemPush['srcWaId']);
            $contacts = $dql->getResult();
            
            // Solo para reforzar que verdaderamente no halla slug que no
            // pertenezcan a la empresa que hizo la solicitud

            // En este bloque lo que hacemos es que del resultado buscamos el
            // primer registro que tenga el waId que recibimos por parametro
            // con la finalidad de obtener su slug y poder filtrar a todos
            // los que tengan el mismo slug.
            $waId = $itemPush['srcWaId'];
            $mismos = array_filter($contacts, function($contac) use ($waId) {
                return $contac->getWaId() == $waId;
            });
            $slug = '';
            if(count($mismos) > 0) {
                $slug = $mismos[0]->getSlug();
            }
            if($slug == '') { return []; }
            $mismos = array_filter($contacts, function($contac) use ($slug) {
                return $contac->getSlug() == $slug;
            });

            // Ya habiendo filtrado todos los registros con el mismo slug
            // lo que hacemos es extraer todos sus tokens
            $filtros = array_map(function($obj) { return $obj->getTkfcm(); }, $mismos);
            $waIds = array_map(function($obj) { return $obj->getWaId(); }, $mismos);
            return [
                'slug'   => $slug,
                'tokens' => array_unique($filtros),
                'waIds'  => array_unique($waIds),
            ];
        }

        return [
            'tokens' => array_unique($filtros),
            'waIds'  => array_unique($waIds),
        ];
    }

}
