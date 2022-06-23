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

    #[ORM\OneToOne(mappedBy: 'cot', targetEntity: Filtros::class, cascade: ['persist', 'remove'])]
    private $filtros;

    public function __construct()
    {
        $this->contactos = new ArrayCollection();
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

    public function getFiltros(): ?Filtros
    {
        return $this->filtros;
    }

    public function setFiltros(Filtros $filtros): self
    {
        // set the owning side of the relation if necessary
        if ($filtros->getCot() !== $this) {
            $filtros->setCot($this);
        }

        $this->filtros = $filtros;

        return $this;
    }
}
