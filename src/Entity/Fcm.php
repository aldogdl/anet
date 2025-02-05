<?php

namespace App\Entity;

use App\Repository\FcmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FcmRepository::class)]
class Fcm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $waId = null;

    #[ORM\Column(length: 20)]
    private ?string $slug = null;

    #[ORM\Column(length: 10)]
    private ?string $device = null;

    #[ORM\Column(length: 255)]
    private ?string $tkfcm = null;

    /** No vendo la marca una lista de todas las marcas que no vende */
    #[ORM\Column(type: Types::JSON)]
    private array $nvm = [];

    #[ORM\Column(length: 1)]
    private ?string $mrnta = null;
   
    public function __construct()
    {
        $this->mrnta = 'd';
        $this->nvm = [];
    }

    /** */
    public function fromJson(array $data) {
        $this->waId   = $data['waId'];
        $this->slug   = $data['slug'];
        $this->device = $data['device'];
        $this->tkfcm  = $data['token'];
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWaid(): ?string
    {
        return $this->waId;
    }

    public function setWaid(string $waid): static
    {
        $this->waId = $waid;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(string $device): static
    {
        $this->device = $device;

        return $this;
    }

    public function getTkfcm(): ?string
    {
        return $this->tkfcm;
    }

    public function setTkfcm(string $tkfcm): static
    {
        $this->tkfcm = $tkfcm;

        return $this;
    }

    public function getNvm(): array
    {
        return $this->nvm;
    }

    public function setNvm(array $nvm): static
    {
        $this->nvm = $nvm;

        return $this;
    }

    public function getMrnta(): ?string
    {
        return $this->mrnta;
    }

    public function setMrnta(string $mrnta): static
    {
        $this->mrnta = $mrnta;

        return $this;
    }
}
