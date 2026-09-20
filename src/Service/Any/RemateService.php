<?php

namespace App\Service\Any;

use App\Entity\Remate;
use App\Repository\RemateRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicio centralizado para la lógica de negocio del dominio Remates.
 */
class RemateService
{
    public function __construct(
        private readonly RemateRepository $repo
    ) {}

    /**
     * Crear o actualizar (upsert) un Remate por iku + ownerSlug
     */
    public function upsert(array $data): array
    {
        $iku = trim((string)($data['iku'] ?? ''));
        $ownerSlug = trim((string)($data['ownerSlug'] ?? $data['slug'] ?? ''));
        $ownerWaId = trim((string)($data['ownerWaId'] ?? $data['waId'] ?? ''));

        if (empty($iku) || empty($ownerSlug)) {
            return [
                'abort' => true,
                'code' => Response::HTTP_BAD_REQUEST,
                'body' => 'Parámetros obligatorios faltantes (iku, ownerSlug)',
            ];
        }

        $remate = $this->repo->findOneByIkuAndOwner($iku, $ownerSlug);
        $isNew = false;

        if (!$remate) {
            $remate = new Remate();
            $remate->setIku($iku);
            $remate->setOwnerSlug($ownerSlug);
            $remate->setCreatedAt(new \DateTimeImmutable('now'));
            $isNew = true;
        }

        // Asignar o preservar remateId
        $remateId = trim((string)($data['remateId'] ?? ''));
        if (!empty($remateId)) {
            $remate->setRemateId($remateId);
        } elseif ($remate->getRemateId() === null || $remate->getRemateId() === '') {
            $remate->setRemateId(uniqid('rem_', true));
        }

        if (!empty($ownerWaId)) {
            $remate->setOwnerWaId($ownerWaId);
        }
        if (isset($data['ownerTaId'])) {
            $remate->setOwnerTaId((int)$data['ownerTaId']);
        }
        if (isset($data['precioRemate'])) {
            $remate->setPrecioRemate((float)$data['precioRemate']);
        }
        if (isset($data['precioOriginal'])) {
            $remate->setPrecioOriginal((float)$data['precioOriginal']);
        }
        if (isset($data['status'])) {
            $remate->setStatus((int)$data['status']);
        }
        if (isset($data['pieza'])) {
            $remate->setPieza((string)$data['pieza']);
        }
        if (isset($data['lado'])) {
            $remate->setLado((string)$data['lado']);
        }
        if (isset($data['poss'])) {
            $remate->setPoss((string)$data['poss']);
        }
        if (array_key_exists('detalles', $data)) {
            $remate->setDetalles($data['detalles'] !== null ? (string)$data['detalles'] : null);
        }
        if (isset($data['mrkId'])) {
            $remate->setMrkId((int)$data['mrkId']);
        }
        if (isset($data['marca'])) {
            $remate->setMarca((string)$data['marca']);
        }
        if (isset($data['mdlId'])) {
            $remate->setMdlId((int)$data['mdlId']);
        }
        if (isset($data['modelo'])) {
            $remate->setModelo((string)$data['modelo']);
        }
        if (isset($data['anioInicio'])) {
            $remate->setAnioInicio((int)$data['anioInicio']);
        }
        if (isset($data['anioFin'])) {
            $remate->setAnioFin((int)$data['anioFin']);
        }
        if (isset($data['fotoThumb'])) {
            $remate->setFotoThumb((string)$data['fotoThumb']);
        }
        if (isset($data['fotoBig'])) {
            $remate->setFotoBig((string)$data['fotoBig']);
        }
        if (isset($data['pathImg'])) {
            $remate->setPathImg((string)$data['pathImg']);
        }
        if (isset($data['pictures']) && is_array($data['pictures'])) {
            $remate->setPictures($data['pictures']);
        }
        if (isset($data['expiresAt']) && !empty($data['expiresAt'])) {
            $exp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string)$data['expiresAt']);
            if ($exp) {
                $remate->setExpiresAt($exp);
            }
        }

        $remate->setUpdatedAt(new \DateTimeImmutable('now'));
        $this->repo->save($remate, true);

        return [
            'abort' => false,
            'code' => Response::HTTP_OK,
            'body' => $remate->toArray(),
            'action' => $isNew ? 'created' : 'updated',
        ];
    }

    /**
     * Actualizar únicamente precioRemate y notas/detalles
     */
    public function updatePrecioYNotas(array $data): array
    {
        $iku = trim((string)($data['iku'] ?? ''));
        $ownerSlug = trim((string)($data['ownerSlug'] ?? $data['slug'] ?? ''));

        if (empty($iku) || empty($ownerSlug)) {
            return [
                'abort' => true,
                'code' => Response::HTTP_BAD_REQUEST,
                'body' => 'Parámetros obligatorios faltantes (iku, ownerSlug)',
            ];
        }

        $remate = $this->repo->findOneByIkuAndOwner($iku, $ownerSlug);
        if (!$remate) {
            return [
                'abort' => true,
                'code' => Response::HTTP_NOT_FOUND,
                'body' => 'Remate no encontrado para el propietario indicado',
            ];
        }

        if ($remate->getOwnerSlug() !== $ownerSlug) {
            return [
                'abort' => true,
                'code' => Response::HTTP_FORBIDDEN,
                'body' => 'Acceso no autorizado al remate',
            ];
        }

        if (isset($data['precioRemate'])) {
            $remate->setPrecioRemate((float)$data['precioRemate']);
        }
        if (array_key_exists('detalles', $data)) {
            $remate->setDetalles($data['detalles'] !== null ? (string)$data['detalles'] : null);
        }

        $remate->setUpdatedAt(new \DateTimeImmutable('now'));
        $this->repo->save($remate, true);

        return [
            'abort' => false,
            'code' => Response::HTTP_OK,
            'updatedAt' => $remate->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'body' => $remate->toArray(),
        ];
    }

    /**
     * Actualizar únicamente el status comercial del remate
     */
    public function updateStatus(array $data): array
    {
        $iku = trim((string)($data['iku'] ?? ''));
        $ownerSlug = trim((string)($data['ownerSlug'] ?? $data['slug'] ?? ''));
        $newStatus = isset($data['status']) ? (int)$data['status'] : null;

        if (empty($iku) || empty($ownerSlug) || $newStatus === null) {
            return [
                'abort' => true,
                'code' => Response::HTTP_BAD_REQUEST,
                'body' => 'Parámetros obligatorios faltantes (iku, ownerSlug, status)',
            ];
        }

        $remate = $this->repo->findOneByIkuAndOwner($iku, $ownerSlug);
        if (!$remate) {
            return [
                'abort' => true,
                'code' => Response::HTTP_NOT_FOUND,
                'body' => 'Remate no encontrado para el propietario indicado',
            ];
        }

        if ($remate->getOwnerSlug() !== $ownerSlug) {
            return [
                'abort' => true,
                'code' => Response::HTTP_FORBIDDEN,
                'body' => 'Acceso no autorizado al remate',
            ];
        }

        $remate->setStatus($newStatus);
        $remate->setUpdatedAt(new \DateTimeImmutable('now'));
        $this->repo->save($remate, true);

        return [
            'abort' => false,
            'code' => Response::HTTP_OK,
            'updatedAt' => $remate->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'body' => $remate->toArray(),
        ];
    }

    /**
     * Eliminar / Cancelar remate
     */
    public function delete(array $data): array
    {
        $iku = trim((string)($data['iku'] ?? ''));
        $ownerSlug = trim((string)($data['ownerSlug'] ?? $data['slug'] ?? ''));

        if (empty($iku) || empty($ownerSlug)) {
            return [
                'abort' => true,
                'code' => Response::HTTP_BAD_REQUEST,
                'body' => 'Parámetros obligatorios faltantes (iku, ownerSlug)',
            ];
        }

        $remate = $this->repo->findOneByIkuAndOwner($iku, $ownerSlug);
        if (!$remate) {
            return [
                'abort' => true,
                'code' => Response::HTTP_NOT_FOUND,
                'body' => 'Remate no encontrado',
            ];
        }

        if ($remate->getOwnerSlug() !== $ownerSlug) {
            return [
                'abort' => true,
                'code' => Response::HTTP_FORBIDDEN,
                'body' => 'Acceso no autorizado al remate',
            ];
        }

        $this->repo->remove($remate, true);

        return [
            'abort' => false,
            'code' => Response::HTTP_OK,
            'body' => 'Remate eliminado correctamente',
        ];
    }

    /**
     * Listar remates propios con paginado
     */
    public function listMisRemates(string $slug, ?string $waId = null, int $page = 1, int $limit = 50): array
    {
        $cleanSlug = trim($slug);
        if (empty($cleanSlug)) {
            return [
                'abort' => true,
                'code' => Response::HTTP_BAD_REQUEST,
                'body' => 'Parámetro slug obligatorio',
            ];
        }

        $cleanWaId = (!empty($waId) && $waId !== '0') ? trim($waId) : null;
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $remates = $this->repo->findByOwner($cleanSlug, $cleanWaId, $page, $limit);
        $total = $this->repo->countByOwner($cleanSlug, $cleanWaId);

        $body = array_map(fn(Remate $r) => $r->toArray(), $remates);

        return [
            'abort' => false,
            'code' => Response::HTTP_OK,
            'body' => $body,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
