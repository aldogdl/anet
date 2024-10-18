<?php

namespace App\Entity;

use App\Repository\ItemsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemsRepository::class)]
class Items
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private ?string $type = null;

    #[ORM\Column(length: 100)]
    private ?string $pieza = null;

    #[ORM\Column(length: 20)]
    private ?string $lado = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $poss = null;

    #[ORM\Column(length: 25)]
    private ?string $marca = null;

    #[ORM\Column(length: 25)]
    private ?string $model = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    private array $anios = [];

    #[ORM\Column(length: 100)]
    private ?string $condicion = null;

    #[ORM\Column(length: 100)]
    private ?string $idItem = null;

    #[ORM\Column(length: 100)]
    private ?string $idCot = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?float $costo = null;

    #[ORM\Column(length: 25)]
    private ?string $origen = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $fotos = null;

    #[ORM\Column(length: 20)]
    private ?string $ownWaId = null;

    #[ORM\Column(length: 50)]
    private ?string $ownSlug = null;

    #[ORM\Column(length: 50)]
    private ?string $place = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $stt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $imgWa = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** */
    public function fromMap(array $item): static
    {   
        $hoy = new \DateTimeImmutable('now');
        $att = $item['attrs'];

        $this->type      = 'solicitud';
        $this->condicion = $item['detalles'];
        $this->idItem    = $item['uuid'];
        $this->fotos     = $item['fotos'];
        $this->ownWaId   = $item['waId'];
        $this->ownSlug   = $item['sellerSlug'];
        $this->price     = $item['price'];
        $this->costo     = $item['originalPrice'];
        $this->pieza     = $att['pieza'];
        $this->lado      = $att['lado'];
        $this->poss      = $att['poss'];
        $this->marca     = $att['marca'];
        $this->model     = $att['modelo'];
        $this->anios     = $att['anios'];
        $this->origen    = $att['origen'];
        $this->idCot     = '';
        $this->place     = '';
        $this->stt       = 0;
        $this->imgWa     = [];
        $this->createdAt = $hoy;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPieza(): ?string
    {
        return $this->pieza;
    }

    public function setPieza(string $pieza): static
    {
        $this->pieza = $pieza;

        return $this;
    }

    public function getLado(): ?string
    {
        return $this->lado;
    }

    public function setLado(string $lado): static
    {
        $this->lado = $lado;

        return $this;
    }

    public function getPoss(): ?string
    {
        return $this->poss;
    }

    public function setPoss(?string $poss): static
    {
        $this->poss = $poss;

        return $this;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setMarca(string $marca): static
    {
        $this->marca = $marca;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getAnios(): array
    {
        return $this->anios;
    }

    public function setAnios(array $anios): static
    {
        $this->anios = $anios;

        return $this;
    }

    public function getCondicion(): ?string
    {
        return $this->condicion;
    }

    public function setCondicion(string $condicion): static
    {
        $this->condicion = $condicion;

        return $this;
    }

    public function getIdItem(): ?string
    {
        return $this->idItem;
    }

    public function setIdItem(string $idItem): static
    {
        $this->idItem = $idItem;

        return $this;
    }

    public function getIdCot(): ?string
    {
        return $this->idCot;
    }

    public function setIdCot(string $idCot): static
    {
        $this->idCot = $idCot;

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

    public function getOrigen(): ?string
    {
        return $this->origen;
    }

    public function setOrigen(string $origen): static
    {
        $this->origen = $origen;

        return $this;
    }

    public function getFotos(): ?array
    {
        return $this->fotos;
    }

    public function setFotos(?array $fotos): static
    {
        $this->fotos = $fotos;

        return $this;
    }

    public function getOwnWaId(): ?string
    {
        return $this->ownWaId;
    }

    public function setOwnWaId(string $ownWaId): static
    {
        $this->ownWaId = $ownWaId;

        return $this;
    }

    public function getOwnSlug(): ?string
    {
        return $this->ownSlug;
    }

    public function setOwnSlug(string $ownSlug): static
    {
        $this->ownSlug = $ownSlug;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(string $place): static
    {
        $this->place = $place;

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

    public function getImgWa(): ?array
    {
        return $this->imgWa;
    }

    public function setImgWa(?array $imgWa): static
    {
        $this->imgWa = $imgWa;

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
}
