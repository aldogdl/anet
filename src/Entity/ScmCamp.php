<?php

namespace App\Entity;

use App\Repository\ScmCampRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScmCampRepository::class)]
class ScmCamp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Campaings::class, inversedBy: 'camps')]
    #[ORM\JoinColumn(nullable: false)]
    private $campaing;

    #[ORM\Column(type: 'string', length: 50)]
    private $target;

    #[ORM\Column(type: 'json')]
    private $src = [];

    #[ORM\ManyToOne(targetEntity: Ng2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $remiter;

    #[ORM\ManyToOne(targetEntity: Ng2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $emiter;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 100)]
    private $sendAt;

    #[ORM\OneToMany(mappedBy: 'camp', targetEntity: ScmReceivers::class)]
    private $receivers;


    public function __construct()
    {
        $this->receivers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaing(): ?Campaings
    {
        return $this->campaing;
    }

    public function setCampaing(?Campaings $campaing): self
    {
        $this->campaing = $campaing;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getSrc(): ?array
    {
        return $this->src;
    }

    public function setSrc(array $src): self
    {
        $this->src = $src;

        return $this;
    }

    public function getRemiter(): ?Ng2Contactos
    {
        return $this->remiter;
    }

    public function setRemiter(?Ng2Contactos $remiter): self
    {
        $this->remiter = $remiter;

        return $this;
    }

    public function getEmiter(): ?Ng2Contactos
    {
        return $this->emiter;
    }

    public function setEmiter(?Ng2Contactos $emiter): self
    {
        $this->emiter = $emiter;

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

    public function getSendAt(): ?string
    {
        return $this->sendAt;
    }

    public function setSendAt(string $sendAt): self
    {
        $this->sendAt = $sendAt;

        return $this;
    }

    /**
     * @return Collection<int, ScmReceivers>
     */
    public function getReceivers(): Collection
    {
        return $this->receivers;
    }

    public function addReceiver(ScmReceivers $receiver): self
    {
        if (!$this->receivers->contains($receiver)) {
            $this->receivers[] = $receiver;
            $receiver->setCamp($this);
        }

        return $this;
    }

    public function removeReceiver(ScmReceivers $receiver): self
    {
        if ($this->receivers->removeElement($receiver)) {
            // set the owning side to null (unless already changed)
            if ($receiver->getCamp() === $this) {
                $receiver->setCamp(null);
            }
        }

        return $this;
    }
}
