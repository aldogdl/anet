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
     * Recupera el vendedor más adecuado para un slug siguiendo el flujo de extracción:
     * 1. Lee el expediente físico /ctcs/<slug>.json y filtra colabs con stt = 1 y roles ROLE_AVO o ROLE_MAIN.
     * 2. Consulta registros en SysCom con LEFT JOIN a NextSeller para los waIds filtrados.
     * 3. Valida y depura candidatos sin taId válido (fuente de verdad SysCom, fallback JSON).
     * 4. Retorna según prioridades:
     *    a) Candidato con NextSeller activo ordenado por menor cantUse y lastUseAt más antiguo (incrementa cantUse).
     *    b) Si no hay en NextSeller, primer candidato de SysCom que no sea MAIN (sin crear NextSeller).
     *    c) Fallback: El colaborador MAIN del JSON (sin crear NextSeller).
     * 
     * @param string $slug
     * @param Fsys $fsys
     * @return array
     */
    public function resolveNextSeller(string $slug, Fsys $fsys): array
    {
        // 1. Leer expediente en disco y filtrar colabs elegibles
        $exp = $fsys->get(AnyPath::$DTACTC, $slug . '.json');
        $colabs = (!empty($exp) && isset($exp['colabs']) && is_array($exp['colabs'])) ? $exp['colabs'] : [];

        $eligibleColabs = [];
        $mainColab = null;

        foreach ($colabs as $colab) {
            $roles = isset($colab['roles']) && is_array($colab['roles']) ? $colab['roles'] : [];
            $isMain = in_array('ROLE_MAIN', $roles, true);
            $isAvo = in_array('ROLE_AVO', $roles, true);
            $stt = isset($colab['stt']) ? (int)$colab['stt'] : 0;
            $waId = isset($colab['waId']) ? trim((string)$colab['waId']) : '';

            // Detectar siempre al colaborador con rol MAIN desde el primer recorrido
            if ($isMain && $mainColab === null) {
                $mainColab = $colab;
            }

            // Colaboradores activos elegibles para rotación
            if ($stt === 1 && ($isAvo || $isMain) && !empty($waId)) {
                $eligibleColabs[$waId] = $colab;
            }
        }

        // Si no se encontró un main explícito, buscar el primero disponible en colabs
        if ($mainColab === null && !empty($colabs)) {
            $mainColab = $colabs[0];
        }

        $mainWaId = ($mainColab && isset($mainColab['waId'])) ? trim((string)$mainColab['waId']) : '';

        // Lista de waIds para la consulta DQL única: activos + el main (sin duplicados)
        $waIdsToQuery = array_keys($eligibleColabs);
        if (!empty($mainWaId) && !in_array($mainWaId, $waIdsToQuery, true)) {
            $waIdsToQuery[] = $mainWaId;
        }

        // 2. Consultar SysCom y NextSeller desde la BD para todos los waIds identificados
        $sysComEntities = [];
        if (!empty($waIdsToQuery)) {
            $dql = 'SELECT sc, ns FROM ' . SysCom::class . ' sc 
                    LEFT JOIN sc.nextSeller ns 
                    WHERE sc.slug = :slug AND sc.waId IN (:waIds)';
            $sysComEntities = $this->_em->createQuery($dql)
                ->setParameter('slug', $slug)
                ->setParameter('waIds', $waIdsToQuery)
                ->getResult();
        }

        // Agrupar entidades SysCom por waId en memoria para acceso rápido
        $sysComByWaId = [];
        foreach ($sysComEntities as $sc) {
            $wId = trim((string)$sc->getWaId());
            $sysComByWaId[$wId][] = $sc;
        }

        // 3. Recorrer y depurar candidatos activos validando taId (BD primero, fallback JSON)
        $validCandidates = [];
        foreach ($sysComEntities as $sc) {
            $waId = trim((string)$sc->getWaId());

            // Solo participan en rotación los colaboradores que estén activos (stt = 1) en el JSON
            if (!isset($eligibleColabs[$waId])) {
                continue;
            }

            $colabJson = $eligibleColabs[$waId];

            // taId: fuente de verdad SysCom, fallback archivo JSON
            $taId = $sc->getTaId();
            if (empty($taId) && isset($colabJson['taId'])) {
                $taId = (string)$colabJson['taId'];
            }

            // Desechar si no cuenta con un taId válido
            if (empty($taId) || (int)$taId <= 0) {
                continue;
            }

            $colabRoles = isset($colabJson['roles']) && is_array($colabJson['roles']) ? $colabJson['roles'] : [];
            $isColabMain = in_array('ROLE_MAIN', $colabRoles, true) || ($waId === $mainWaId);
            $name = $sc->getName() ?: ($colabJson['fullName'] ?? $colabJson['nombre'] ?? $colabJson['name'] ?? '');

            $validCandidates[] = [
                'sysCom' => $sc,
                'nextSeller' => $sc->getNextSeller(),
                'waId' => $waId,
                'taId' => (int)$taId,
                'name' => $name,
                'device' => $sc->getDevice() ?: 'any',
                'isMain' => $isColabMain,
            ];
        }

        $selectedCandidate = null;

        // 4. Evaluar condiciones en orden de prioridad

        // Prioridad A: El siguiente vendedor de NextSeller (con stt = 1) con menor cantUse y lastUseAt más antiguo
        $nextSellerCandidates = array_filter($validCandidates, function ($item) {
            return ($item['nextSeller'] instanceof NextSeller) && $item['nextSeller']->getStt() === 1;
        });

        if (!empty($nextSellerCandidates)) {
            usort($nextSellerCandidates, function ($a, $b) {
                $cantA = $a['nextSeller']->getCantUse() ?? 0;
                $cantB = $b['nextSeller']->getCantUse() ?? 0;
                if ($cantA === $cantB) {
                    $timeA = $a['nextSeller']->getLastUseAt() ? $a['nextSeller']->getLastUseAt()->getTimestamp() : 0;
                    $timeB = $b['nextSeller']->getLastUseAt() ? $b['nextSeller']->getLastUseAt()->getTimestamp() : 0;
                    return $timeA <=> $timeB;
                }
                return $cantA <=> $cantB;
            });

            $selectedCandidate = $nextSellerCandidates[0];

            // Incrementar cantUse (+1) y actualizar fecha de uso en NextSeller
            $winnerNs = $selectedCandidate['nextSeller'];
            $winnerNs->setCantUse(($winnerNs->getCantUse() ?? 0) + 1);
            $winnerNs->setLastUseAt(new \DateTimeImmutable('now'));
            $this->_em->flush();
        }

        // Prioridad B: No hay registros en NextSeller, el primero que resulte de SysCom y que no sea main
        if ($selectedCandidate === null) {
            foreach ($validCandidates as $candidate) {
                if (!$candidate['isMain']) {
                    $selectedCandidate = $candidate;
                    break;
                }
            }
        }

        // Prioridad C: El main desde la memoria de SysCom (o del JSON)
        if ($selectedCandidate !== null) {
            $finalWaId = $selectedCandidate['waId'];
            $finalTaId = $selectedCandidate['taId'];
            $finalName = $selectedCandidate['name'];
            $finalDevice = $selectedCandidate['device'];
            $finalIsMain = $selectedCandidate['isMain'];
        } else {
            // Resolver datos del MAIN desde los registros en memoria de SysCom con fallback al JSON
            $finalWaId = $mainWaId;
            $finalTaId = 0;
            $finalName = '';
            $finalDevice = 'any';
            $finalIsMain = true;

            if ($mainColab) {
                $finalName = $mainColab['fullName'] ?? $mainColab['nombre'] ?? $mainColab['name'] ?? '';
                $finalTaId = isset($mainColab['taId']) ? (int)$mainColab['taId'] : 0;
            }

            // Priorizar datos desde SysCom en memoria para el waId del MAIN
            if (!empty($mainWaId) && isset($sysComByWaId[$mainWaId])) {
                foreach ($sysComByWaId[$mainWaId] as $scMain) {
                    if ($scMain->getTaId() && (int)$scMain->getTaId() > 0) {
                        $finalTaId = (int)$scMain->getTaId();
                    }
                    if ($scMain->getName()) {
                        $finalName = (string)$scMain->getName();
                    }
                    if ($scMain->getDevice()) {
                        $finalDevice = (string)$scMain->getDevice();
                    }
                    if ($finalTaId > 0) {
                        break;
                    }
                }
            }
        }

        return [
            'found' => !empty($finalWaId) && !empty($finalTaId),
            'slug' => $slug,
            'waId' => $finalWaId,
            'taId' => (int)$finalTaId,
            'name' => $finalName,
            'device' => $finalDevice,
            'isMain' => $finalIsMain,
        ];
    }
}


