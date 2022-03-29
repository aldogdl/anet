<?php

namespace App\Entity;

use App\Repository\AO2ModelosRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AO2ModelosRepository::class)]
class AO2Modelos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: AO1Marcas::class, inversedBy: 'modelos')]
    #[ORM\JoinColumn(nullable: false)]
    private $marca;

    #[ORM\Column(type: 'string', length: 60)]
    private $nombre;

    #[ORM\OneToMany(mappedBy: 'modelo', targetEntity: AutosReg::class)]
    private $regs;

    #[ORM\OneToMany(mappedBy: 'modelo', targetEntity: PiezasReg::class)]
    private $piezasReg;

    public function __construct()
    {
        $this->regs = new ArrayCollection();
        $this->piezasReg = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * @return Collection<int, AutosReg>
     */
    public function getRegs(): Collection
    {
        return $this->regs;
    }

    public function addReg(AutosReg $reg): self
    {
        if (!$this->regs->contains($reg)) {
            $this->regs[] = $reg;
            $reg->setModelo($this);
        }

        return $this;
    }

    public function removeReg(AutosReg $reg): self
    {
        if ($this->regs->removeElement($reg)) {
            // set the owning side to null (unless already changed)
            if ($reg->getModelo() === $this) {
                $reg->setModelo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PiezasReg>
     */
    public function getPiezasReg(): Collection
    {
        return $this->piezasReg;
    }

    public function addPiezasReg(PiezasReg $piezasReg): self
    {
        if (!$this->piezasReg->contains($piezasReg)) {
            $this->piezasReg[] = $piezasReg;
            $piezasReg->setModelo($this);
        }

        return $this;
    }

    public function removePiezasReg(PiezasReg $piezasReg): self
    {
        if ($this->piezasReg->removeElement($piezasReg)) {
            // set the owning side to null (unless already changed)
            if ($piezasReg->getModelo() === $this) {
                $piezasReg->setModelo(null);
            }
        }

        return $this;
    }
}
