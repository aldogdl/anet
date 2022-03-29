<?php

namespace App\Entity;

use App\Repository\PiezasRegRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PiezasRegRepository::class)]
class PiezasReg
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: PiezasName::class, inversedBy: 'piezasReg')]
    #[ORM\JoinColumn(nullable: false)]
    private $pieza;

    #[ORM\ManyToOne(targetEntity: AO1Marcas::class, inversedBy: 'piezasReg')]
    #[ORM\JoinColumn(nullable: false)]
    private $marca;

    #[ORM\ManyToOne(targetEntity: AO2Modelos::class, inversedBy: 'piezasReg')]
    #[ORM\JoinColumn(nullable: false)]
    private $modelo;

    #[ORM\Column(type: 'integer')]
    private $anioDesde;

    #[ORM\Column(type: 'integer')]
    private $anioHasta;

    #[ORM\Column(type: 'string', length: 30)]
    private $lado;

    #[ORM\Column(type: 'string', length: 30)]
    private $posicion;

    #[ORM\Column(type: 'integer')]
    private $cantReg;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPieza(): ?PiezasName
    {
        return $this->pieza;
    }

    public function setPieza(?PiezasName $pieza): self
    {
        $this->pieza = $pieza;

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

    public function getAnioDesde(): ?int
    {
        return $this->anioDesde;
    }

    public function setAnioDesde(int $anioDesde): self
    {
        $this->anioDesde = $anioDesde;

        return $this;
    }

    public function getAnioHasta(): ?int
    {
        return $this->anioHasta;
    }

    public function setAnioHasta(int $anioHasta): self
    {
        $this->anioHasta = $anioHasta;

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

    public function getCantReg(): ?int
    {
        return $this->cantReg;
    }

    public function setCantReg(int $cantReg): self
    {
        $this->cantReg = $cantReg;

        return $this;
    }
}
