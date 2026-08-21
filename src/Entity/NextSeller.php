<?php

namespace App\Entity;

use App\Repository\NextSellerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NextSellerRepository::class)]
class NextSeller
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'nextSeller', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?SysCom $seller = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastUseAt = null;

    #[ORM\Column]
    private ?int $stt = null;

    #[ORM\Column]
    private ?int $cantUse = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $registerdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeller(): ?SysCom
    {
        return $this->seller;
    }

    public function setSeller(SysCom $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getLastUseAt(): ?\DateTimeImmutable
    {
        return $this->lastUseAt;
    }

    public function setLastUseAt(\DateTimeImmutable $lastUseAt): static
    {
        $this->lastUseAt = $lastUseAt;

        return $this;
    }

    public function getStt(): ?int
    {
        return $this->stt;
    }

    public function setStt(int $stt): static
    {
        $this->stt = $stt;

        return $this;
    }

    public function getCantUse(): ?int
    {
        return $this->cantUse;
    }

    public function setCantUse(int $cantUse): static
    {
        $this->cantUse = $cantUse;

        return $this;
    }

    public function getRegisterdAt(): ?\DateTimeImmutable
    {
        return $this->registerdAt;
    }

    public function setRegisterdAt(\DateTimeImmutable $registerdAt): static
    {
        $this->registerdAt = $registerdAt;

        return $this;
    }
}
