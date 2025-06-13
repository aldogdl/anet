<?php

namespace App\Entity;

use App\Repository\PubsRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PubsRepository::class)]
class Pubs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $iku = null;

    #[ORM\Column(length: 150)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    private ?string $detalle = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?float $costo = null;

    #[ORM\Column(length: 50)]
    private ?string $ftoRef = null;
    
    #[ORM\Column]
    private array $fotos = [];

    #[ORM\Column(length: 25)]
    private ?string $appSlug = null;

    #[ORM\Column(length: 15)]
    private ?string $appWaId = null;

    #[ORM\Column]
    private ?bool $isDf = null;

    #[ORM\Column]
    private ?int $stt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $ftec = null;

    #[ORM\Column(length: 25)]
    private ?string $ikuOwn = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->costo = 0.0;
        $this->fotos = [];
        $this->stt = 0;
        $this->detalle = '';
        $this->isDf = false;
    }

    /** */
    public function buildToSave(): array
    {
        $map = [
            'iku'=> $this->iku,
            'cd' => $this->code,
            'pr' => $this->price,
            'fr' => $this->ftoRef,
            'oik'=> $this->ikuOwn,
            'osl'=> $this->appSlug,
            'owi'=> $this->appWaId,
            'df' => $this->isDf,
            'cr' => $this->createdAt->format('Y-m-d H:i:s'),
            'ft' => $this->ftec,
        ];
        if(count($this->fotos) > 0) {
            $map['fts'] = $this->fotos;
        }
        if($this->detalle != '') {
            $map['dt'] = $this->detalle;
        }
        if($this->costo > 0) {
            $map['ct'] = $this->costo;
        }
        return $map;
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

    public function getAppSlug(): ?string
    {
        return $this->appSlug;
    }

    public function setAppSlug(string $appSlug): static
    {
        $this->appSlug = $appSlug;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCosto(): ?float
    {
        return $this->costo;
    }

    public function setCosto(float $costo): static
    {
        $this->costo = $costo;

        return $this;
    }

    public function isIsDf(): ?bool
    {
        return $this->isDf;
    }

    public function setIsDf(bool $isDf): static
    {
        $this->isDf = $isDf;

        return $this;
    }

    public function getFotos(): array
    {
        return $this->fotos;
    }

    public function setFotos(array $fotos): static
    {
        $this->fotos = $fotos;

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

    public function getFtoRef(): ?string
    {
        return $this->ftoRef;
    }

    public function setFtoRef(string $ftoRef): static
    {
        $this->ftoRef = $ftoRef;

        return $this;
    }

    public function getStt(): ?int
    {
        return $this->stt;
    }

    public function setStt(int $stt): static
    {
        $this->stt = $stt;

        return $this;
    }

    public function getFtec(): ?string
    {
        return $this->ftec;
    }

    public function setFtec(string $ftec): static
    {
        $this->ftec = $ftec;

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
