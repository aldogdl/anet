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

    #[ORM\Column(type: 'string', length: 100)]
    private $pieza;

    /**
     * a = Alta Gama, Manejo solo marcas prestigiosas
     * b = Comerciales, Manejo solo marcas comunes
     * c = Comerciales, Manejo Multimarcas
     * d = Restricción, Solo esta, Manejo solo esta
     * e = Excepción, Todas Excepto esta, no manejo esta
     * t = Tambien, Filtro que especifica lo que realmente maneja ya que lo ha cotizado
     */
    #[ORM\Column(type: 'string', length: 15)]
    private $grupo;

    #[ORM\ManyToOne(targetEntity: PiezasName::class)]
    private $pza;

    #[ORM\Column(type: 'string', length: 4)]
    private $anioD;

    #[ORM\Column(type: 'string', length: 4)]
    private $anioH;

    public function __construct()
    {
        $this->anioD = '0';
        $this->anioH = '0';
        $this->pieza = '0';
    }

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

    public function getAnioD(): ?string
    {
        return $this->anioD;
    }

    public function setAnioD(string $anioD): self
    {
        $this->anioD = $anioD;

        return $this;
    }

    public function getAnioH(): ?string
    {
        return $this->anioH;
    }

    public function setAnioH(string $anioH): self
    {
        $this->anioH = $anioH;

        return $this;
    }

    ///
    public function toArray(): Array
    {
        return [
            'id' => $this->id,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'pieza' => $this->pieza,
            'grupo' => $this->grupo,
            'pza' => $this->pza,
            'anioD' => $this->anioD,
            'anioH' => $this->anioH,
        ];
    }
}
