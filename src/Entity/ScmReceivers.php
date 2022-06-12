<?php

namespace App\Entity;

use App\Repository\ScmReceiversRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScmReceiversRepository::class)]
class ScmReceivers
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: ScmCamp::class, inversedBy: 'receivers')]
    #[ORM\JoinColumn(nullable: false)]
    private $camp;

    #[ORM\ManyToOne(targetEntity: NG2Contactos::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $receiver;

    #[ORM\Column(type: 'string', length: 3)]
    private $stt;

    #[ORM\Column(type: 'datetime_immutable')]
    private $sendAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $readAt;

    public function __construct()
    {
        return $this->sendAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCamp(): ?ScmCamp
    {
        return $this->camp;
    }

    public function setCamp(?ScmCamp $camp): self
    {
        $this->camp = $camp;

        return $this;
    }

    public function getReceiver(): ?NG2Contactos
    {
        return $this->receiver;
    }

    public function setReceiver(?NG2Contactos $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getStt(): ?string
    {
        return $this->stt;
    }

    public function setStt(string $stt): self
    {
        $this->stt = $stt;

        return $this;
    }

    public function getSendAt(): ?\DateTimeImmutable
    {
        return $this->sendAt;
    }

    public function setSendAt(\DateTimeImmutable $sendAt): self
    {
        $this->sendAt = $sendAt;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): self
    {
        $this->readAt = $readAt;

        return $this;
    }
}
