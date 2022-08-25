<?php

namespace App\Entity;

use App\Repository\FiltrosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FiltrosRepository::class)]
class Filtros
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: NG1Empresas::class, inversedBy: 'filtros')]
    private $emp;

    #[ORM\ManyToOne(targetEntity: AO1Marcas::class)]
    private $marca;

    #[ORM\ManyToOne(targetEntity: AO2Modelos::class)]
    private $modelo;

    #[ORM\Column(type: 'integer')]
    private $anio;

    #[ORM\Column(type: 'string', length: 100)]
    private $pieza;

    /**
     * e = Excepción, Todas menos esta, no manejo esta
     * r = Restricción, Solo esta, Manejo solo esta
     * g = Alta Gama, Manejo solo marcas prestigiosas
     * c = Comerciales, Manejo solo marcas comunes
     */
    #[ORM\Column(type: 'string', length: 1)]
    private $grupo;

    #[ORM\ManyToOne(targetEntity: PiezasName::class)]
    private $pza;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmp(): ?NG1Empresas
    {
        return $this->emp;
    }

    public function setEmp(?NG1Empresas $emp): self
    {
        $this->emp = $emp;

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

    public function getAnio(): ?int
    {
        return $this->anio;
    }

    public function setAnio(int $anio): self
    {
        $this->anio = $anio;

        return $this;
    }

    public function getPieza(): ?string
    {
        return $this->pieza;
    }

    public function setPieza(string $pieza): self
    {
        $this->pieza = $pieza;

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

    public function getPza(): ?PiezasName
    {
        return $this->pza;
    }

    public function setPza(?PiezasName $pza): self
    {
        $this->pza = $pza;

        return $this;
    }

    
}
