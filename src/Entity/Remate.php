<?php

namespace App\Entity;

use App\Repository\RemateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemateRepository::class)]
#[ORM\Table(name: 'remate')]
#[ORM\UniqueConstraint(name: 'uniq_remate_iku_owner', columns: ['iku', 'owner_slug'])]
#[ORM\Index(name: 'idx_remate_owner', columns: ['owner_slug', 'owner_wa_id', 'status'])]
#[ORM\Index(name: 'idx_remate_mrk_mdl', columns: ['mrk_id', 'mdl_id', 'status'])]
#[ORM\Index(name: 'idx_remate_status_dates', columns: ['status', 'created_at', 'expires_at'])]
class Remate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $remateId = null;

    #[ORM\Column(length: 100)]
    private ?string $iku = null;

    #[ORM\Column(length: 100)]
    private ?string $ownerSlug = null;

    #[ORM\Column(length: 50)]
    private ?string $ownerWaId = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $ownerTaId = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    private ?float $precioRemate = 0.0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.0])]
    private ?float $precioOriginal = 0.0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $status = 0;

    #[ORM\Column(length: 255)]
    private ?string $pieza = '';

    #[ORM\Column(length: 50, options: ['default' => 'A'])]
    private ?string $lado = 'A';

    #[ORM\Column(length: 50, options: ['default' => 'A'])]
    private ?string $poss = 'A';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detalles = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $mrkId = 0;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private ?string $marca = '';

    #[ORM\Column(options: ['default' => 0])]
    private ?int $mdlId = 0;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private ?string $modelo = '';

    #[ORM\Column(options: ['default' => 0])]
    private ?int $anioInicio = 0;

    #[ORM\Column(options: ['default' => 9999])]
    private ?int $anioFin = 9999;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $fotoThumb = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $fotoBig = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $pathImg = '';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pictures = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->ownerTaId = 0;
        $this->precioRemate = 0.0;
        $this->precioOriginal = 0.0;
        $this->status = 0;
        $this->lado = 'A';
        $this->poss = 'A';
        $this->mrkId = 0;
        $this->mdlId = 0;
        $this->anioInicio = 0;
        $this->anioFin = 9999;
        $this->fotoThumb = '';
        $this->fotoBig = '';
        $this->pathImg = '';
        $this->pictures = [];
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRemateId(): ?string
    {
        return $this->remateId;
    }

    public function setRemateId(?string $remateId): self
    {
        $this->remateId = $remateId;
        return $this;
    }

    public function getIku(): ?string
    {
        return $this->iku;
    }

    public function setIku(string $iku): self
    {
        $this->iku = $iku;
        return $this;
    }

    public function getOwnerSlug(): ?string
    {
        return $this->ownerSlug;
    }

    public function setOwnerSlug(string $ownerSlug): self
    {
        $this->ownerSlug = $ownerSlug;
        return $this;
    }

    public function getOwnerWaId(): ?string
    {
        return $this->ownerWaId;
    }

    public function setOwnerWaId(string $ownerWaId): self
    {
        $this->ownerWaId = $ownerWaId;
        return $this;
    }

    public function getOwnerTaId(): ?int
    {
        return $this->ownerTaId;
    }

    public function setOwnerTaId(int $ownerTaId): self
    {
        $this->ownerTaId = $ownerTaId;
        return $this;
    }

    public function getPrecioRemate(): ?float
    {
        return $this->precioRemate;
    }

    public function setPrecioRemate(float $precioRemate): self
    {
        $this->precioRemate = $precioRemate;
        return $this;
    }

    public function getPrecioOriginal(): ?float
    {
        return $this->precioOriginal;
    }

    public function setPrecioOriginal(float $precioOriginal): self
    {
        $this->precioOriginal = $precioOriginal;
        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPieza(): ?string
    {
        return $this->pieza;
    }

    public function setPieza(string $pieza): self
    {
        $this->pieza = $pieza;
        return $this;
    }

    public function getLado(): ?string
    {
        return $this->lado;
    }

    public function setLado(?string $lado): self
    {
        $this->lado = $lado ?? 'A';
        return $this;
    }

    public function getPoss(): ?string
    {
        return $this->poss;
    }

    public function setPoss(?string $poss): self
    {
        $this->poss = $poss ?? 'A';
        return $this;
    }

    public function getDetalles(): ?string
    {
        return $this->detalles;
    }

    public function setDetalles(?string $detalles): self
    {
        $this->detalles = $detalles;
        return $this;
    }

    public function getMrkId(): ?int
    {
        return $this->mrkId;
    }

    public function setMrkId(int $mrkId): self
    {
        $this->mrkId = $mrkId;
        return $this;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setMarca(?string $marca): self
    {
        $this->marca = $marca ?? '';
        return $this;
    }

    public function getMdlId(): ?int
    {
        return $this->mdlId;
    }

    public function setMdlId(int $mdlId): self
    {
        $this->mdlId = $mdlId;
        return $this;
    }

    public function getModelo(): ?string
    {
        return $this->modelo;
    }

    public function setModelo(?string $modelo): self
    {
        $this->modelo = $modelo ?? '';
        return $this;
    }

    public function getAnioInicio(): ?int
    {
        return $this->anioInicio;
    }

    public function setAnioInicio(int $anioInicio): self
    {
        $this->anioInicio = $anioInicio;
        return $this;
    }

    public function getAnioFin(): ?int
    {
        return $this->anioFin;
    }

    public function setAnioFin(int $anioFin): self
    {
        $this->anioFin = $anioFin;
        return $this;
    }

    public function getFotoThumb(): ?string
    {
        return $this->fotoThumb;
    }

    public function setFotoThumb(?string $fotoThumb): self
    {
        $this->fotoThumb = $fotoThumb ?? '';
        return $this;
    }

    public function getFotoBig(): ?string
    {
        return $this->fotoBig;
    }

    public function setFotoBig(?string $fotoBig): self
    {
        $this->fotoBig = $fotoBig ?? '';
        return $this;
    }

    public function getPathImg(): ?string
    {
        return $this->pathImg;
    }

    public function setPathImg(?string $pathImg): self
    {
        $this->pathImg = $pathImg ?? '';
        return $this;
    }

    public function getPictures(): ?array
    {
        return $this->pictures ?? [];
    }

    public function setPictures(?array $pictures): self
    {
        $this->pictures = $pictures ?? [];
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remateId' => $this->remateId ?? ($this->id !== null ? (string)$this->id : null),
            'iku' => $this->iku,
            'ownerSlug' => $this->ownerSlug,
            'ownerWaId' => $this->ownerWaId,
            'ownerTaId' => $this->ownerTaId,
            'precioRemate' => $this->precioRemate,
            'precioOriginal' => $this->precioOriginal,
            'status' => $this->status,
            'pieza' => $this->pieza,
            'lado' => $this->lado,
            'poss' => $this->poss,
            'detalles' => $this->detalles,
            'mrkId' => $this->mrkId,
            'marca' => $this->marca,
            'mdlId' => $this->mdlId,
            'modelo' => $this->modelo,
            'anioInicio' => $this->anioInicio,
            'anioFin' => $this->anioFin,
            'fotoThumb' => $this->fotoThumb,
            'fotoBig' => $this->fotoBig,
            'pathImg' => $this->pathImg,
            'pictures' => $this->pictures ?? [],
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
