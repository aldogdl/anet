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

    #[ORM\OneToOne(inversedBy: 'filtros', targetEntity: NG1Empresas::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $cot;

    #[ORM\Column(type: 'json', nullable: true)]
    private $restris = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $exceps = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $espes = [];

    #[ORM\Column(type: 'boolean')]
    private $isFav;

    #[ORM\Column(type: 'string', length: 50)]
    private $plan;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCot(): ?NG1Empresas
    {
        return $this->cot;
    }

    public function setCot(NG1Empresas $cot): self
    {
        $this->cot = $cot;

        return $this;
    }
    
    public function getRestris(): ?array
    {
        return $this->restris;
    }

    public function setRestris(?array $restris): self
    {
        $this->restris = $restris;

        return $this;
    }

    public function getExceps(): ?array
    {
        return $this->exceps;
    }

    public function setExceps(?array $exceps): self
    {
        $this->exceps = $exceps;

        return $this;
    }

    public function getEspes(): ?array
    {
        return $this->espes;
    }

    public function setEspes(?array $espes): self
    {
        $this->espes = $espes;

        return $this;
    }

    public function getIsFav(): ?bool
    {
        return $this->isFav;
    }

    public function setIsFav(bool $isFav): self
    {
        $this->isFav = $isFav;

        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): self
    {
        $this->plan = $plan;

        return $this;
    }
}
