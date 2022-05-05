<?php

namespace App\Entity;

use App\Repository\CampaingsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaingsRepository::class)]
class Campaings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $titulo;

    #[ORM\Column(type: 'string', length: 255)]
    private $despec;

    #[ORM\OneToMany(mappedBy: 'campaing', targetEntity: ScmCamp::class)]
    private $camps;

    #[ORM\Column(type: 'smallint')]
    private $priority;

    public function __construct()
    {
        $this->camps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getDespec(): ?string
    {
        return $this->despec;
    }

    public function setDespec(string $despec): self
    {
        $this->despec = $despec;

        return $this;
    }

    /**
     * @return Collection<int, ScmCamp>
     */
    public function getCamps(): Collection
    {
        return $this->camps;
    }

    public function addCamp(ScmCamp $camp): self
    {
        if (!$this->camps->contains($camp)) {
            $this->camps[] = $camp;
            $camp->setCampaing($this);
        }

        return $this;
    }

    public function removeCamp(ScmCamp $camp): self
    {
        if ($this->camps->removeElement($camp)) {
            // set the owning side to null (unless already changed)
            if ($camp->getCampaing() === $this) {
                $camp->setCampaing(null);
            }
        }

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
