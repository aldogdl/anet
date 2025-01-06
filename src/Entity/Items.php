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

    #[ORM\Column(type: Types::JSON, nullable: true)]
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

    #[ORM\Column(length: 15)]
    private ?string $ownMlId = null;

    #[ORM\Column]
    private ?int $idAnet = null;

    #[ORM\Column]
    private ?int $pzaId = null;

    #[ORM\Column]
    private ?int $mrkId = null;

    #[ORM\Column]
    private ?int $mdlId = null;

    #[ORM\Column(length: 155)]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 155)]
    private ?string $permalink = null;

    #[ORM\Column(length: 10)]
    private ?string $source = null;

    #[ORM\Column(type: Types::JSON)]
    private array $generik = [];

    #[ORM\Column(type: Types::JSON)]
    private array $matchs = [];

    #[ORM\Column]
    private ?int $calif = null;

    public function __construct()
    {
        $this->ownMlId = "";
        $this->idAnet = -1;
        $this->pzaId = 0;
        $this->mrkId = 0;
        $this->mdlId = 0;
        $this->thumbnail = "";
        $this->permalink = "";
        $this->source = "form";
        $this->generik = [];
        $this->matchs = [];
        $this->calif = 5;
    }

    /** */
    public function fromMapItem(array $item): static
    {   
        if(array_key_exists('id', $item)) {
            $this->id = $item['id'];
        }

        $hoy = new \DateTimeImmutable('now');
        $this->type      = $item['type'];
        $this->condicion = $item['condicion'];
        $this->idItem    = $item['idItem'];
        $this->fotos     = $item['fotos'];
        $this->ownWaId   = $item['ownWaId'];
        $this->ownSlug   = $item['ownSlug'];
        $this->price     = $item['price'];
        $this->costo     = $item['costo'];
        $this->pieza     = $item['pieza'];
        $this->lado      = $item['lado'];
        $this->poss      = $item['poss'];
        $this->marca     = $item['marca'];
        $this->model     = (array_key_exists('model', $item)) ? $item['model'] : $item['modelo'];
        $this->anios     = $item['anios'];
        $this->origen    = $item['origen'];
        $this->idCot     = $item['idCot'];
        $this->place     = $item['place'];
        $this->stt       = $item['stt'];
        $this->ownMlId   = $item['ownMlId'];
        $this->idAnet    = $item['idAnet'];
        $this->pzaId     = $item['pzaId'];
        $this->mrkId     = $item['mrkId'];
        $this->mdlId     = $item['mdlId'];
        $this->thumbnail = $item['thumbnail'];
        $this->permalink = $item['permalink'];
        $this->source    = $item['source'];
        $this->generik   = $item['generik'];
        $this->matchs    = $item['matchs'];
        $this->calif     = $item['calif'];
        $this->imgWa     = [];
        $this->createdAt = $hoy;
        return $this;
    }

    /** */
    public function toJsonForHead(): array
    {   
        $fecha = $this->createdAt;
        return [
            'type'      => $this->type,
            'id'        => $this->id,
            'idItem'    => $this->idItem,
            'ownSlug'   => $this->ownSlug,
            'ownWaId'   => $this->ownWaId,
            'thumbnail' => $this->thumbnail,
            'source'    => $this->source,
            'checkinSR' => $fecha->format("Y-m-d\TH:i:s.v")
        ];
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

    public function getOwnMlId(): ?string
    {
        return $this->ownMlId;
    }

    public function setOwnMlId(string $ownMlId): static
    {
        $this->ownMlId = $ownMlId;

        return $this;
    }

    public function getIdAnet(): ?int
    {
        return $this->idAnet;
    }

    public function setIdAnet(int $idAnet): static
    {
        $this->idAnet = $idAnet;

        return $this;
    }

    public function getPzaId(): ?int
    {
        return $this->pzaId;
    }

    public function setPzaId(int $pzaId): static
    {
        $this->pzaId = $pzaId;

        return $this;
    }

    public function getMrkId(): ?int
    {
        return $this->mrkId;
    }

    public function setMrkId(int $mrkId): static
    {
        $this->mrkId = $mrkId;

        return $this;
    }

    public function getMdlId(): ?int
    {
        return $this->mdlId;
    }

    public function setMdlId(int $mdlId): static
    {
        $this->mdlId = $mdlId;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getPermalink(): ?string
    {
        return $this->permalink;
    }

    public function setPermalink(string $permalink): static
    {
        $this->permalink = $permalink;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getGenerik(): array
    {
        return $this->generik;
    }

    public function setGenerik(array $generik): static
    {
        $this->generik = $generik;

        return $this;
    }

    public function getMatchs(): array
    {
        return $this->matchs;
    }

    public function setMatchs(array $matchs): static
    {
        $this->matchs = $matchs;

        return $this;
    }

    public function getCalif(): ?int
    {
        return $this->calif;
    }

    public function setCalif(int $calif): static
    {
        $this->calif = $calif;

        return $this;
    }
}
