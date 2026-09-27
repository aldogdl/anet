<?php

namespace App\Service\Any;

use App\Entity\Remate;
use App\Repository\RemateRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicio centralizado para la lógica de negocio del dominio Remates.
 */
class RemateService
{
    public function __construct(
        private readonly RemateRepository $repo,
        private readonly Fsys $fsys
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

    /**
     * Consulta rápida de disponibilidad por remateId
     * Si no existe o remateId está vacío, devuelve status = 501 (eliminado / no disponible)
     * NO retorna 404 para remates inexistentes. Utiliza consulta escalar ultra rápida.
     */
    public function checkStatus(string $remateId): array
    {
        $cleanId = trim($remateId);
        $cleanId = preg_replace('/^RMT:/i', '', $cleanId);
        $cleanId = trim($cleanId);

        if (empty($cleanId)) {
            return [
                'remateId' => '',
                'status' => 501,
            ];
        }

        $statusData = $this->repo->findStatusByRemateIdOrId($cleanId);
        if (!$statusData) {
            return [
                'remateId' => $cleanId,
                'status' => 501,
            ];
        }

        return [
            'remateId' => !empty($statusData['remateId']) ? (string)$statusData['remateId'] : (!empty($statusData['iku']) ? (string)$statusData['iku'] : (string)$statusData['id']),
            'status' => (int)$statusData['status'],
        ];
    }

    /**
     * Obtiene el payload completo y sanitizado para la hidratación integral de un Remate comunitario.
     * Retorna los datos técnicos del remate, los datos de la empresa y la lista de colaboradores SIN contraseñas ni secretos.
     */
    public function getHydrationPayload(string $remateIdOrIku, ?string $slug = null): ?array
    {
        $cleanId = trim($remateIdOrIku);
        $cleanSlug = $slug !== null ? trim($slug) : null;

        if (empty($cleanId)) {
            return null;
        }

        // 1. Buscar entidad Remate por remateId / id o por iku + slug
        $remate = $this->repo->findByRemateIdOrId($cleanId);
        if (!$remate && !empty($cleanSlug)) {
            $remate = $this->repo->findOneByIkuAndOwner($cleanId, $cleanSlug);
        }

        if (!$remate) {
            return null;
        }

        $ownerSlug = trim((string)$remate->getOwnerSlug());
        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ownerSlug);

        // 2. Mapear DTO de Remate completo
        $remateData = [
            'id' => $remate->getId(),
            'remateId' => $remate->getRemateId() ?? '',
            'iku' => $remate->getIku() ?? '',
            'ownerSlug' => $ownerSlug,
            'ownerWaId' => $remate->getOwnerWaId() ?? '',
            'ownerTaId' => $remate->getOwnerTaId() ?? 0,
            'precioRemate' => $remate->getPrecioRemate() ?? 0.0,
            'precioOriginal' => $remate->getPrecioOriginal() ?? 0.0,
            'status' => $remate->getStatus() ?? 0,
            'pieza' => $remate->getPieza() ?? '',
            'lado' => $remate->getLado() ?? 'A',
            'poss' => $remate->getPoss() ?? 'A',
            'detalles' => $remate->getDetalles(),
            'mrkId' => $remate->getMrkId() ?? 0,
            'marca' => $remate->getMarca() ?? '',
            'mdlId' => $remate->getMdlId() ?? 0,
            'modelo' => $remate->getModelo() ?? '',
            'anioInicio' => $remate->getAnioInicio() ?? 0,
            'anioFin' => $remate->getAnioFin() ?? 9999,
            'fotoThumb' => $remate->getFotoThumb() ?? '',
            'fotoBig' => $remate->getFotoBig() ?? '',
            'pathImg' => $remate->getPathImg() ?? '',
            'pictures' => $remate->getPictures() ?? [],
            'createdAt' => $remate->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $remate->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'expiresAt' => $remate->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];

        // 3. Cargar y sanitizar expediente de la empresa
        $company = [
            'empresa' => $ownerSlug,
            'slug' => $safeSlug,
            'localidad' => '',
            'logo' => '',
            'plan' => '',
            'ynksmx' => '',
            'categoria' => '',
        ];
        $colabs = [];

        if (!empty($safeSlug)) {
            $exp = $this->fsys->get(AnyPath::$DTACTC, $safeSlug . '.json');
            if (is_array($exp)) {
                $company['empresa'] = (string)($exp['empresa'] ?? $exp['name'] ?? $ownerSlug);
                $company['localidad'] = (string)($exp['localidad'] ?? '');
                $company['logo'] = (string)($exp['logo'] ?? '');
                $company['plan'] = (string)($exp['plan'] ?? '');
                $company['ynksmx'] = (string)($exp['ynksmx'] ?? '');
                $company['categoria'] = (string)($exp['categoria'] ?? '');

                if (isset($exp['colabs']) && is_array($exp['colabs'])) {
                    foreach ($exp['colabs'] as $colab) {
                        if (!is_array($colab)) {
                            continue;
                        }
                        // REGLA CRÍTICA DE SEGURIDAD: Remover contraseñas y secretos
                        unset($colab['pass']);
                        $colabs[] = $colab;
                    }
                }
            }
        }

        return [
            'ok' => true,
            'remate' => $remateData,
            'company' => $company,
            'colabs' => $colabs,
        ];
    }
}
