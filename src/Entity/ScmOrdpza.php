<?php

namespace App\Entity;

use App\Repository\ScmOrdpzaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScmOrdpzaRepository::class)]
class ScmOrdpza
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /** 
     * Indica la prioridad si es una orden o una pieza la que se va a enviar
     * si es true entonces es una orden
     * si es false entonces es una pieza
     */
    #[ORM\Column(type: 'boolean')]
    private $prioridad;

    /**
     * Indica si se esta procesando o aun no.
     * 0 sin procesar
     * 1 procesando
     * 2 lista para borrar
     */
    #[ORM\Column(type: 'integer')]
    private $acc;

    /**
     * Por medio del numero de status sabemos que tipo de mensaje hay que enviar
     */
    #[ORM\Column(type: 'string', length: 3)]
    private $msg;

    /**
     * Sabemos si el registro fue realizado por la SCP o el centinela
     */
    #[ORM\Column(type: 'string', length: 10)]
    private $sys;

    #[ORM\ManyToOne(targetEntity: Ordenes::class)]
    private $orden;

    #[ORM\ManyToOne(targetEntity: OrdenPiezas::class)]
    private $pieza;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $own;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $avo;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrioridad(): ?bool
    {
        return $this->prioridad;
    }

    public function setPrioridad(bool $prioridad): self
    {
        $this->prioridad = $prioridad;

        return $this;
    }

    public function getAcc(): ?int
    {
        return $this->acc;
    }

    public function setAcc(int $acc): self
    {
        $this->acc = $acc;

        return $this;
    }

    public function getMsg(): ?string
    {
        return $this->msg;
    }

    public function setMsg(string $msg): self
    {
        $this->msg = $msg;

        return $this;
    }

    public function getSys(): ?string
    {
        return $this->sys;
    }

    public function setSys(string $sys): self
    {
        $this->sys = $sys;

        return $this;
    }

    public function getOrden(): ?Ordenes
    {
        return $this->orden;
    }

    public function setOrden(?Ordenes $orden): self
    {
        $this->orden = $orden;

        return $this;
    }

    public function getPieza(): ?OrdenPiezas
    {
        return $this->pieza;
    }

    public function setPieza(?OrdenPiezas $pieza): self
    {
        $this->pieza = $pieza;

        return $this;
    }

    public function getOwn(): ?NG2Contactos
    {
        return $this->own;
    }

    public function setOwn(?NG2Contactos $own): self
    {
        $this->own = $own;

        return $this;
    }

    public function getAvo(): ?NG2Contactos
    {
        return $this->avo;
    }

    public function setAvo(?NG2Contactos $avo): self
    {
        $this->avo = $avo;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
