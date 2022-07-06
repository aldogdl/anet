<?php

namespace App\Entity;

use App\Repository\OrdenPiezasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdenPiezasRepository::class)]
class OrdenPiezas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Ordenes::class, inversedBy: 'piezas')]
    #[ORM\JoinColumn(nullable: false)]
    private $orden;

    #[ORM\Column(type: 'string', length: 3)]
    private $est;

    #[ORM\Column(type: 'string', length: 3)]
    private $stt;

    #[ORM\Column(type: 'string', length: 100)]
    private $piezaName;

    #[ORM\Column(type: 'string', length: 50)]
    private $origen;

    #[ORM\Column(type: 'string', length: 30)]
    private $lado;

    #[ORM\Column(type: 'string', length: 30)]
    private $posicion;

    #[ORM\Column(type: 'array')]
    private $fotos = [];

    #[ORM\Column(type: 'string', length: 255)]
    private $obs;

    #[ORM\OneToMany(mappedBy: 'pieza', targetEntity: OrdenResps::class, orphanRemoval: true)]
    private $resps;

    #[ORM\Column(type: 'bigint')]
    private $idHive;

    public function __construct()
    {
        $this->resps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEst(): ?string
    {
        return $this->est;
    }

    public function setEst(string $est): self
    {
        $this->est = $est;

        return $this;
    }

    public function getStt(): ?string
    {
        return $this->stt;
    }

    public function setStt(string $stt): self
    {
        $this->stt = $stt;

        return $this;
    }

    public function getPiezaName(): ?string
    {
        return $this->piezaName;
    }

    public function setPiezaName(string $piezaName): self
    {
        $this->piezaName = $piezaName;

        return $this;
    }

    public function getOrigen(): ?string
    {
        return $this->origen;
    }

    public function setOrigen(string $origen): self
    {
        $this->origen = $origen;

        return $this;
    }

    public function getLado(): ?string
    {
        return $this->lado;
    }

    public function setLado(string $lado): self
    {
        $this->lado = $lado;

        return $this;
    }

    public function getPosicion(): ?string
    {
        return $this->posicion;
    }

    public function setPosicion(string $posicion): self
    {
        $this->posicion = $posicion;

        return $this;
    }

    public function getFotos(): ?array
    {
        return $this->fotos;
    }

    public function setFotos(array $fotos): self
    {
        $this->fotos = $fotos;

        return $this;
    }

    public function getObs(): ?string
    {
        return $this->obs;
    }

    public function setObs(string $obs): self
    {
        $this->obs = $obs;

        return $this;
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

    /**
     * @return Collection<int, OrdenResps>
     */
    public function getResps(): Collection
    {
        return $this->resps;
    }

    public function addResp(OrdenResps $resp): self
    {
        if (!$this->resps->contains($resp)) {
            $this->resps[] = $resp;
            $resp->setPieza($this);
        }

        return $this;
    }

    public function removeResp(OrdenResps $resp): self
    {
        if ($this->resps->removeElement($resp)) {
            // set the owning side to null (unless already changed)
            if ($resp->getPieza() === $this) {
                $resp->setPieza(null);
            }
        }

        return $this;
    }

    public function getIdHive(): ?string
    {
        return $this->idHive;
    }

    public function setIdHive(string $idHive): self
    {
        $this->idHive = $idHive;

        return $this;
    }
}
