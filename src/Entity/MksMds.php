<?php

namespace App\Entity;

use App\Repository\MksMdsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MksMdsRepository::class)]
class MksMds
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private ?string $name = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    private array $vars = [];

    #[ORM\Column]
    private ?int $idMrk = null;

    #[ORM\Column(length: 10)]
    private ?string $idMlb = null;

    #[ORM\Column(length: 15)]
    private ?string $idRdc = null;

    #[ORM\Column(length: 10)]
    private ?string $idAdo = null;

    public function __construct()
    {
        $this->idMrk = 0;
        $this->idMlb = '0';
        $this->idRdc = '0';
        $this->idAdo = '0';
        $this->vars = [];
    }

    /** */
    private function parseToTitulo(String $cadena): String
    {
        $excepciones = array("viii", "dfsk");

        if(mb_strpos($cadena, '-') !== false) {

            $palabras = explode("-", $cadena);
            foreach ($palabras as &$palabra) {
                $quitarGuion = (strlen($palabra) <= 2) ? true : false;
            }
            if($quitarGuion) {
                $cadena = str_replace("-", "", $cadena);
            }else{
                $cadena = str_replace("-", " ", $cadena);
            }
        }

        $palabras = explode(" ", $cadena);

        foreach ($palabras as &$palabra) {
            if (ctype_digit($palabra[0])) {
                $palabra = strtoupper($palabra);
                $palabra = str_replace('-', "", $palabra);
            } else if (strlen($palabra) <= 3) {
                $palabra = strtoupper($palabra);
                $palabra = str_replace('-', "", $palabra);
            } else if (preg_match('/^[A-Z]+[\d]+$/i', $palabra)) {
                $palabra = strtoupper($palabra);
                $palabra = str_replace('-', "", $palabra);
            } else if (in_array(strtolower($palabra), $excepciones)) {
                $palabra = strtoupper($palabra);
            } else {
                $palabra = ucfirst(strtolower($palabra));
            }
        }

        return implode(" ", $palabras);
    }

    /** */
    public function toVariBasic(String $name, String $quita = '-', String $sust = ''): String
    {
        $name = mb_strtolower($name);
        $name = trim($name);
        $name = str_replace($quita, $sust, $name);
        return $name;
    }

    /** 
    * Los nombres que vengan con letras y numeros o numeros y letras las separamos cn un guion
    */
    public function toVariEspetial(String $name): String
    {
        $name = mb_strtolower($name);
        $name = trim($name);
        if(preg_match('/[a-zA-Z]/', $name) && preg_match('/\d/', $name)) {
            return trim(preg_replace('/(\d+)/', '-$1-', $name), '-');
        } else {
            return $name;
        }
    }

    /** */
    public function fromFileMrk(array $mrk): static
    {
        $this->idMlb = $mrk['idMl'];
        return $this->setName($mrk['label']);
    }

    /** */
    public function fromFileMdl(int $idMrk, array $mdl): static
    {
        $this->idMrk = $idMrk;
        $this->idMlb = $mdl['idMl'];
        $this->setName($mdl['label']);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $this->parseToTitulo($name);
        $slug = $this->toVariBasic($name, ' ', '_');
        $slug = $this->toVariBasic($slug, '-', '_');
        $this->vars[] = $slug;
        $this->vars[] = $this->toVariEspetial($name);

        $this->vars[] = $this->toVariBasic($slug, '_', '-');
        $this->vars[] = $this->toVariBasic($slug, '_', ' ');
        $this->vars[] = $this->toVariBasic($slug, '_', '');
        $this->vars = array_unique($this->vars);
        return $this;
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    public function setVars(array $vars): static
    {
        $this->vars = $vars;

        return $this;
    }

    public function getIdMrk(): ?int
    {
        return $this->idMrk;
    }

    public function setIdMrk(int $idMrk): static
    {
        $this->idMrk = $idMrk;

        return $this;
    }

    public function getIdMlb(): ?string
    {
        return $this->idMlb;
    }

    public function setIdMlb(string $idMlb): static
    {
        $this->idMlb = $idMlb;

        return $this;
    }

    public function getIdRdc(): ?string
    {
        return $this->idRdc;
    }

    public function setIdRdc(string $idRdc): static
    {
        $this->idRdc = $idRdc;

        return $this;
    }

    public function getIdAdo(): ?string
    {
        return $this->idAdo;
    }

    public function setIdAdo(string $idAdo): static
    {
        $this->idAdo = $idAdo;

        return $this;
    }
}
