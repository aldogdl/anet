<?php

namespace App\Entity;

use App\Repository\AO1MarcasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AO1MarcasRepository::class)]
class AO1Marcas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 30)]
    private $nombre;

    #[ORM\Column(type: 'string', length: 30)]
    private $logo;

    #[ORM\OneToMany(mappedBy: 'marca', targetEntity: AO2Modelos::class)]
    private $modelos;

    #[ORM\OneToMany(mappedBy: 'marca', targetEntity: AutosReg::class)]
    private $regs;

    #[ORM\OneToMany(mappedBy: 'marca', targetEntity: PiezasReg::class)]
    private $piezasReg;

    #[ORM\Column(type: 'string', length: 1)]
    private $grupo;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
        $this->regs = new ArrayCollection();
        $this->piezasReg = new ArrayCollection();
        $this->grupo = 'c';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @return Collection<int, AO2Modelos>
     */
    public function getModelos(): Collection
    {
        return $this->modelos;
    }

    public function addModelo(AO2Modelos $modelo): self
    {
        if (!$this->modelos->contains($modelo)) {
            $this->modelos[] = $modelo;
            $modelo->setMarca($this);
        }

        return $this;
    }

    public function removeModelo(AO2Modelos $modelo): self
    {
        if ($this->modelos->removeElement($modelo)) {
            // set the owning side to null (unless already changed)
            if ($modelo->getMarca() === $this) {
                $modelo->setMarca(null);
            }
        }

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
            $reg->setMarca($this);
        }

        return $this;
    }

    public function removeReg(AutosReg $reg): self
    {
        if ($this->regs->removeElement($reg)) {
            // set the owning side to null (unless already changed)
            if ($reg->getMarca() === $this) {
                $reg->setMarca(null);
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
            $piezasReg->setMarca($this);
        }

        return $this;
    }

    public function removePiezasReg(PiezasReg $piezasReg): self
    {
        if ($this->piezasReg->removeElement($piezasReg)) {
            // set the owning side to null (unless already changed)
            if ($piezasReg->getMarca() === $this) {
                $piezasReg->setMarca(null);
            }
        }

        return $this;
    }

    public function getGrupo(): ?string
    {
        return $this->grupo;
    }

    public function setGrupo(string $grupo): self
    {
        $this->grupo = $grupo;

        return $this;
    }

}
