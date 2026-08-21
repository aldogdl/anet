<?php

namespace App\Repository;

use App\Entity\NextSeller;
use App\Entity\SysCom;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\Fsys\AnyPath;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NextSeller>
 *
 * @method NextSeller|null find($id, $lockMode = null, $lockVersion = null)
 * @method NextSeller|null findOneBy(array $criteria, array $orderBy = null)
 * @method NextSeller[]    findAll()
 * @method NextSeller[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NextSellerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NextSeller::class);
    }

    /**
     * Busca el registro de NextSeller por la entidad SysCom utilizando DQL
     */
    public function findBySysCom(SysCom $sysCom): ?NextSeller
    {
        $dql = 'SELECT ns FROM ' . NextSeller::class . ' ns WHERE ns.seller = :seller';
        return $this->_em->createQuery($dql)
            ->setParameter('seller', $sysCom)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * Sincroniza / Upsert condicional de NextSeller según el status recibido:
     * - Si $status == -1: Si existe, se elimina de la base de datos.
     * - Si $status == 0: Si existe, se actualiza stt = 0.
     * - Si $status >= 1: Si no existe, se inserta nuevo (cantUse = 0, stt = $status, lastUseAt = now, registerdAt = now).
     *                    Si ya existe, se asegura de que stt = $status.
     */
    public function syncSellerStatus(SysCom $sysCom, int $status): ?NextSeller
    {
        $existing = $this->findBySysCom($sysCom);

        if ($status === -1) {
            if ($existing) {
                $this->_em->remove($existing);
                $this->_em->flush();
            }
            return null;
        }

        if ($status === 0) {
            if ($existing) {
                $existing->setStt(0);
                $this->_em->flush();
                return $existing;
            }
            return null;
        }

        // Si status es 1 o mayor
        if ($existing) {
            if ($existing->getStt() !== $status) {
                $existing->setStt($status);
                $this->_em->flush();
            }
            return $existing;
        }

        $now = new \DateTimeImmutable('now');
        $nextSeller = new NextSeller();
        $nextSeller->setSeller($sysCom);
        $nextSeller->setStt($status);
        $nextSeller->setCantUse(0);
        $nextSeller->setLastUseAt($now);
        $nextSeller->setRegisterdAt($now);

        $this->_em->persist($nextSeller);
        $this->_em->flush();

        return $nextSeller;
    }

    /**
     * Evalúa y sincroniza de manera masiva y eficiente el estado (stt) de los colaboradores de un negocio:
     * - Consulta todos los SysCom y NextSeller del slug en una sola consulta DQL con LEFT JOIN.
     * - Si stt == -1 (Baja): Elimina NextSeller y SysCom de la BD, y excluye el colaborador del array retornado.
     * - Si stt == 0 (Descanso): Si existe NextSeller, lo actualiza a stt = 0.
     * - Si stt >= 1 (Activo): Si tiene rol ROLE_MAIN o ROLE_AVO y existe en SysCom, asegura o crea NextSeller.
     *                         Si no tiene roles de venta, elimina cualquier NextSeller residual.
     * 
     * @param string $slug
     * @param array $colabs
     * @return array Array depurado de colaboradores listo para guardarse en disco.
     */
    public function evalAndCleanColabs(string $slug, array $colabs): array
    {
        if (empty($colabs)) {
            return [];
        }

        // Consultar todos los SysCom y sus NextSeller asociados en 1 sola consulta (LEFT JOIN)
        $dql = 'SELECT sc, ns FROM ' . SysCom::class . ' sc LEFT JOIN sc.nextSeller ns WHERE sc.slug = :slug';
        $sysComEntities = $this->_em->createQuery($dql)
            ->setParameter('slug', $slug)
            ->getResult();

        // Agrupar entidades SysCom por waId en memoria
        $sysComByWaId = [];
        foreach ($sysComEntities as $sc) {
            $sysComByWaId[$sc->getWaId()][] = $sc;
        }

        $cleanedColabs = [];
        $hasDbChanges = false;

        foreach ($colabs as $colab) {
            $waId = isset($colab['waId']) ? (string)$colab['waId'] : '';
            $stt = isset($colab['stt']) ? (int)$colab['stt'] : 0;
            $roles = isset($colab['roles']) && is_array($colab['roles']) ? $colab['roles'] : [];
            $isEligibleRole = in_array('ROLE_MAIN', $roles) || in_array('ROLE_AVO', $roles);

            $devices = $sysComByWaId[$waId] ?? [];

            if ($stt === -1) {
                // Dar de baja: eliminar SysCom y su NextSeller de la BD para todos los dispositivos del waId
                foreach ($devices as $sc) {
                    $nextSeller = $sc->getNextSeller();
                    if ($nextSeller) {
                        $this->_em->remove($nextSeller);
                    }
                    $this->_em->remove($sc);
                    $hasDbChanges = true;
                }
                // Al no agregarlo a $cleanedColabs, queda eliminado del JSON final antes de guardar en disco
                continue;
            }

            if ($stt === 0) {
                // Descanso: inactivar NextSeller si existe
                foreach ($devices as $sc) {
                    $nextSeller = $sc->getNextSeller();
                    if ($nextSeller && $nextSeller->getStt() !== 0) {
                        $nextSeller->setStt(0);
                        $hasDbChanges = true;
                    }
                }
            } elseif ($isEligibleRole) {
                // Activo (stt >= 1) con rol de venta: asegurar/crear NextSeller en los dispositivos registrados
                foreach ($devices as $sc) {
                    $nextSeller = $sc->getNextSeller();
                    if ($nextSeller) {
                        if ($nextSeller->getStt() !== $stt) {
                            $nextSeller->setStt($stt);
                            $hasDbChanges = true;
                        }
                    } else {
                        $now = new \DateTimeImmutable('now');
                        $newNs = new NextSeller();
                        $newNs->setSeller($sc);
                        $newNs->setStt($stt);
                        $newNs->setCantUse(0);
                        $newNs->setLastUseAt($now);
                        $newNs->setRegisterdAt($now);
                        $this->_em->persist($newNs);
                        $hasDbChanges = true;
                    }
                }
            } else {
                // Activo pero sin rol de vendedor: no debe tener NextSeller
                foreach ($devices as $sc) {
                    $nextSeller = $sc->getNextSeller();
                    if ($nextSeller) {
                        $this->_em->remove($nextSeller);
                        $hasDbChanges = true;
                    }
                }
            }

            $cleanedColabs[] = $colab;
        }

        if ($hasDbChanges) {
            $this->_em->flush();
        }

        return array_values($cleanedColabs);
    }

    /**
     * Recupera el vendedor más adecuado (menor cantUse / uso más antiguo) con stt = 1 para un slug.
     * Si no hay vendedores activos en NextSeller, hace fallback usando el $callbackWaId recibido.
     * Completa taId o waId desde /ctcs/<slug>.json si no existen o están vacíos en BD.
     * Actualiza cantUse (+1) y lastUseAt (now) para el vendedor ganador.
     */
    public function resolveNextSeller(string $slug, string $callbackWaId, Fsys $fsys): array
    {
        // 1. Buscar vendedores activos ordenados por menor cantidad de uso y fecha más antigua
        $dql = 'SELECT ns, sc FROM ' . NextSeller::class . ' ns 
                JOIN ns.seller sc 
                WHERE sc.slug = :slug AND ns.stt = 1 
                ORDER BY ns.cantUse ASC, ns.lastUseAt ASC';
        
        $sellers = $this->_em->createQuery($dql)
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getResult();

        $selectedNs = !empty($sellers) ? $sellers[0] : null;
        $isFallback = false;
        $sysCom = null;

        if ($selectedNs instanceof NextSeller) {
            $sysCom = $selectedNs->getSeller();
            // Incrementar uso y actualizar fecha
            $selectedNs->setCantUse(($selectedNs->getCantUse() ?? 0) + 1);
            $selectedNs->setLastUseAt(new \DateTimeImmutable('now'));
            $this->_em->flush();
        } else {
            $isFallback = true;
            // Fallback: Intentar buscar en SysCom por slug y callbackWaId si viene proporcionado
            if (!empty($callbackWaId)) {
                $dqlSc = 'SELECT sc FROM ' . SysCom::class . ' sc WHERE sc.slug = :slug AND sc.waId = :waId';
                $sysCom = $this->_em->createQuery($dqlSc)
                    ->setParameter('slug', $slug)
                    ->setParameter('waId', $callbackWaId)
                    ->setMaxResults(1)
                    ->getOneOrNullResult();
            }
            if (!$sysCom) {
                // Si aún no hay SysCom, buscar cualquier SysCom del slug
                $dqlScAny = 'SELECT sc FROM ' . SysCom::class . ' sc WHERE sc.slug = :slug';
                $sysCom = $this->_em->createQuery($dqlScAny)
                    ->setParameter('slug', $slug)
                    ->setMaxResults(1)
                    ->getOneOrNullResult();
            }
        }

        // Extraer datos base
        $waId = $sysCom ? (string)$sysCom->getWaId() : $callbackWaId;
        $taId = $sysCom && $sysCom->getTaId() ? (string)$sysCom->getTaId() : '';
        $name = $sysCom ? (string)$sysCom->getName() : '';
        $device = $sysCom ? (string)$sysCom->getDevice() : '';

        // Si falta taId o waId, o si no hubo SysCom, consultar expediente físico /ctcs/<slug>.json
        if (empty($taId) || empty($waId) || empty($name)) {
            $exp = $fsys->get(AnyPath::$DTACTC, $slug . '.json');
            if (!empty($exp) && isset($exp['colabs']) && is_array($exp['colabs'])) {
                $matchedColab = null;
                // Buscar por waId si existe
                if (!empty($waId)) {
                    foreach ($exp['colabs'] as $c) {
                        if (isset($c['waId']) && (string)$c['waId'] === $waId) {
                            $matchedColab = $c;
                            break;
                        }
                    }
                }
                // Si no coincide, buscar el MAIN o el primero
                if (!$matchedColab) {
                    foreach ($exp['colabs'] as $c) {
                        if (isset($c['roles']) && is_array($c['roles']) && in_array('ROLE_MAIN', $c['roles'], true)) {
                            $matchedColab = $c;
                            break;
                        }
                    }
                    if (!$matchedColab && !empty($exp['colabs'])) {
                        $matchedColab = $exp['colabs'][0];
                    }
                }

                if ($matchedColab) {
                    if (empty($waId) && isset($matchedColab['waId'])) {
                        $waId = (string)$matchedColab['waId'];
                    }
                    if (empty($taId) && isset($matchedColab['taId'])) {
                        $taId = (string)$matchedColab['taId'];
                    }
                    if (empty($name) && isset($matchedColab['name'])) {
                        $name = (string)$matchedColab['name'];
                    }
                }
            }
        }

        return [
            'found' => !empty($waId) || !empty($taId),
            'slug' => $slug,
            'waId' => $waId,
            'taId' => !empty($taId) ? (int)$taId : 0,
            'name' => $name,
            'device' => $device,
            'isFallback' => $isFallback,
        ];
    }
}


