<?php

namespace App\Entity;

use App\Repository\AutosRegRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AutosRegRepository::class)]
class AutosReg
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: AO1Marcas::class, inversedBy: 'regs')]
    #[ORM\JoinColumn(nullable: false)]
    private $marca;

    #[ORM\ManyToOne(targetEntity: AO2Modelos::class, inversedBy: 'regs')]
    #[ORM\JoinColumn(nullable: false)]
    private $modelo;

    #[ORM\Column(type: 'integer')]
    private $anio;

    #[ORM\Column(type: 'boolean')]
    private $isNac;

    #[ORM\Column(type: 'integer')]
    private $cantReq;

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

    public function getModelo(): ?AO2Modelos
    {
        return $this->modelo;
    }

    public function setModelo(?AO2Modelos $modelo): self
    {
        $this->modelo = $modelo;

        return $this;
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

    public function getIsNac(): ?bool
    {
        return $this->isNac;
    }

    public function setIsNac(bool $isNac): self
    {
        $this->isNac = $isNac;

        return $this;
    }

    public function getCantReq(): ?int
    {
        return $this->cantReq;
    }

    public function setCantReq(int $cantReq): self
    {
        $this->cantReq = $cantReq;

        return $this;
    }
}
