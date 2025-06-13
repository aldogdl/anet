<?php

namespace App\Entity;

use App\Repository\SolsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SolsRepository::class)]
class Sols
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    private ?string $detalle = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 15)]
    private ?string $appWaId = null;

    #[ORM\Column(length: 25)]
    private ?string $appSlug = null;

    #[ORM\Column(length: 25)]
    private ?string $iku = null;

    #[ORM\Column(length: 25)]
    private ?string $ikuOwn = null;

    public function __construct()
    {
        $this->detalle = '';
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDetalle(): ?string
    {
        return $this->detalle;
    }

    public function setDetalle(string $detalle): static
    {
        $this->detalle = $detalle;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAppWaId(): ?string
    {
        return $this->appWaId;
    }

    public function setAppWaId(string $appWaId): static
    {
        $this->appWaId = $appWaId;

        return $this;
    }

    public function getAppSlug(): ?string
    {
        return $this->appSlug;
    }

    public function setAppSlug(string $appSlug): static
    {
        $this->appSlug = $appSlug;

        return $this;
    }

    public function getIku(): ?string
    {
        return $this->iku;
    }

    public function setIku(string $iku): static
    {
        $this->iku = $iku;

        return $this;
    }

    public function getIkuOwn(): ?string
    {
        return $this->ikuOwn;
    }

    public function setIkuOwn(string $ikuOwn): static
    {
        $this->ikuOwn = $ikuOwn;

        return $this;
    }

}
