<?php

namespace App\Entity;

use App\Repository\OrdenesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdenesRepository::class)]
class Ordenes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $own;

    #[ORM\ManyToOne(targetEntity: AO1Marcas::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $marca;

    #[ORM\ManyToOne(targetEntity: AO2Modelos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $modelo;

    #[ORM\Column(type: 'integer')]
    private $anio;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    private $avo;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 3)]
    private $est;

    #[ORM\Column(type: 'string', length: 3)]
    private $stt;

    #[ORM\OneToMany(mappedBy: 'orden', targetEntity: OrdenPiezas::class)]
    private $piezas;

    #[ORM\Column(type: 'boolean')]
    private $isNac;

    public function __construct()
    {
        $this->piezas = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnio(): ?int
    {
        return $this->anio;
    }

    public function setAnio(int $anio): self
    {
        $this->anio = $anio;

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

    public function getIsNac(): ?bool
    {
        return $this->isNac;
    }

    public function setIsNac(bool $isNac): self
    {
        $this->isNac = $isNac;

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

    public function getMarca(): ?AO1Marcas
    {
        return $this->marca;
    }

    public function setMarca(?AO1Marcas $marca): self
    {
        $this->marca = $marca;

        return $this;
    }

    public function getModelo(): ?AO2Modelos
    {
        return $this->modelo;
    }

    public function setModelo(?AO2Modelos $modelo): self
    {
        $this->modelo = $modelo;

        return $this;
    }

    public function getAvo(): ?NG2Contactos
    {
        return $this->avo;
    }

    public function setAvo(?NG2Contactos $avo): self
    {
        $this->avo = $avo;

        return $this;
    }

    /**
     * @return Collection<int, OrdenPiezas>
     */
    public function getPiezas(): Collection
    {
        return $this->piezas;
    }

    public function addPieza(OrdenPiezas $pieza): self
    {
        if (!$this->piezas->contains($pieza)) {
            $this->piezas[] = $pieza;
            $pieza->setOrden($this);
        }

        return $this;
    }

    public function removePieza(OrdenPiezas $pieza): self
    {
        if ($this->piezas->removeElement($pieza)) {
            // set the owning side to null (unless already changed)
            if ($pieza->getOrden() === $this) {
                $pieza->setOrden(null);
            }
        }

        return $this;
    }

}
