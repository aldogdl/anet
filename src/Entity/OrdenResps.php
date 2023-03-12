<?php

namespace App\Entity;

use App\Repository\OrdenRespsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdenRespsRepository::class)]
class OrdenResps
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Ordenes::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $orden;

    #[ORM\ManyToOne(targetEntity: OrdenPiezas::class, inversedBy: 'resps')]
    #[ORM\JoinColumn(nullable: false)]
    private $pieza;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $own;

    #[ORM\Column(type: 'string', length: 10)]
    private $costo;

    #[ORM\Column(type: 'string', length: 255)]
    private $observs;

    #[ORM\Column(type: 'array', nullable: true)]
    private $fotos = [];

    #[ORM\Column(type: 'array')]
    private $status = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 10)]
    private $precio;

    /** */
    public function __construct()
    {
      $this->costo = '0';
      $this->precio = '0';
      $this->observs = '0';
      $this->fotos = [];
      $this->status = [
        'own' => 1,
        'eval' => 0
      ];
      $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrden(): ?Ordenes
    {
        return $this->orden;
    }

    public function setOrden(?Ordenes $orden): self
    {
        $this->orden = $orden;

        return $this;
    }

    public function getPieza(): ?OrdenPiezas
    {
        return $this->pieza;
    }

    public function setPieza(?OrdenPiezas $pieza): self
    {
        $this->pieza = $pieza;

        return $this;
    }

    public function getOwn(): ?NG2Contactos
    {
        return $this->own;
    }

    public function setOwn(?NG2Contactos $own): self
    {
        $this->own = $own;

        return $this;
    }

    public function getCosto(): ?string
    {
        return $this->costo;
    }

    public function setCosto(string $costo): self
    {
        $this->costo = $costo;

        return $this;
    }

    public function getObservs(): ?string
    {
        return $this->observs;
    }

    public function setObservs(string $observs): self
    {
        $this->observs = $observs;

        return $this;
    }

    public function getFotos(): ?array
    {
        return $this->fotos;
    }

    public function setFotos(?array $fotos): self
    {
        $this->fotos = $fotos;

        return $this;
    }

    public function getStatus(): ?array
    {
        return $this->status;
    }

    public function setStatus(array $status): self
    {
        $this->status = $status;

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

    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(string $precio): self
    {
        $this->precio = $precio;

        return $this;
    }

    /** */
    public function toArray(): Array
    {
        return [
            'id' => $this->id,
            'pieza' => $this->pieza,
            'own' => $this->own,
            'costo' => $this->costo,
            'observs' => $this->observs,
            'fotos' => $this->fotos,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'precio' => $this->precio,
        ];

    }

}
