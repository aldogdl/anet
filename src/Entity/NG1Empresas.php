<?php

namespace App\Entity;

use App\Repository\NG1EmpresasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NG1EmpresasRepository::class)]
class NG1Empresas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 150)]
    private $nombre;

    #[ORM\Column(type: 'string', length: 150)]
    private $domicilio;

    #[ORM\Column(type: 'integer')]
    private $cp;

    #[ORM\Column(type: 'boolean')]
    private $isLocal;

    #[ORM\Column(type: 'string', length: 20)]
    private $telFijo;

    #[ORM\Column(type: 'string', length: 100)]
    private $latLng;

    #[ORM\OneToMany(mappedBy: 'empresa', targetEntity: NG2Contactos::class, orphanRemoval: true)]
    private $contactos;

    #[ORM\OneToMany(mappedBy: 'emp', targetEntity: Filtros::class)]
    private $filtros;

    public function __construct()
    {
        $this->contactos = new ArrayCollection();
        $this->filtros = new ArrayCollection();
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

    public function getDomicilio(): ?string
    {
        return $this->domicilio;
    }

    public function setDomicilio(string $domicilio): self
    {
        $this->domicilio = $domicilio;

        return $this;
    }

    public function getCp(): ?int
    {
        return $this->cp;
    }

    public function setCp(int $cp): self
    {
        $this->cp = $cp;

        return $this;
    }

    public function getIsLocal(): ?bool
    {
        return $this->isLocal;
    }

    public function setIsLocal(bool $isLocal): self
    {
        $this->isLocal = $isLocal;

        return $this;
    }

    public function getTelFijo(): ?string
    {
        return $this->telFijo;
    }

    public function setTelFijo(string $telFijo): self
    {
        $this->telFijo = $telFijo;

        return $this;
    }

    public function getLatLng(): ?string
    {
        return $this->latLng;
    }

    public function setLatLng(string $latLng): self
    {
        $this->latLng = $latLng;

        return $this;
    }

    /**
     * @return Collection<int, NG2Contactos>
     */
    public function getContactos(): Collection
    {
        return $this->contactos;
    }

    public function addContacto(NG2Contactos $contacto): self
    {
        if (!$this->contactos->contains($contacto)) {
            $this->contactos[] = $contacto;
            $contacto->setEmpresa($this);
        }

        return $this;
    }

    public function removeContacto(NG2Contactos $contacto): self
    {
        if ($this->contactos->removeElement($contacto)) {
            // set the owning side to null (unless already changed)
            if ($contacto->getEmpresa() === $this) {
                $contacto->setEmpresa(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Filtros>
     */
    public function getFiltros(): Collection
    {
        return $this->filtros;
    }

    public function addFiltro(Filtros $filtro): self
    {
        if (!$this->filtros->contains($filtro)) {
            $this->filtros[] = $filtro;
            $filtro->setEmp($this);
        }

        return $this;
    }

    public function removeFiltro(Filtros $filtro): self
    {
        if ($this->filtros->removeElement($filtro)) {
            // set the owning side to null (unless already changed)
            if ($filtro->getEmp() === $this) {
                $filtro->setEmp(null);
            }
        }

        return $this;
    }

    ///
    public function toArray(): Array
    {
        $filts = [];
        $rota = count($this->filtros);
        for ($i=0; $i < $rota; $i++) { 
            $filts[] = $this->filtros[$i]->toArray();
        }
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'domicilio' => $this->domicilio,
            'cp' => $this->cp,
            'isLocal' => $this->isLocal,
            'telFijo' => $this->telFijo,
            'latLng' => $this->latLng,
            'filtros' => $filts,
        ];
    }
}
