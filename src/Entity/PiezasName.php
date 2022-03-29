<?php

namespace App\Entity;

use App\Repository\PiezasNameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PiezasNameRepository::class)]
class PiezasName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100)]
    private $nombre;

    #[ORM\OneToMany(mappedBy: 'pieza', targetEntity: PiezasReg::class)]
    private $piezasReg;

    public function __construct()
    {
        $this->piezasReg = new ArrayCollection();
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
            $piezasReg->setPieza($this);
        }

        return $this;
    }

    public function removePiezasReg(PiezasReg $piezasReg): self
    {
        if ($this->piezasReg->removeElement($piezasReg)) {
            // set the owning side to null (unless already changed)
            if ($piezasReg->getPieza() === $this) {
                $piezasReg->setPieza(null);
            }
        }

        return $this;
    }

}
