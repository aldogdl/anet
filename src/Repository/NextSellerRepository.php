<?php

namespace App\Repository;

use App\Entity\NextSeller;
use App\Entity\SysCom;
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
}

