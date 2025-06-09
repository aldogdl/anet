<?php

namespace App\Entity;

use App\Repository\UsComRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UsComRepository::class)]
class UsCom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** El Slug del dueño de la app */
    #[ORM\Column(length: 45)]
    private ?string $ownApp = null;
    
    /** El waId del usuario de la app, dueño del este registro y de este token */
    #[ORM\Column(length: 15)]
    private ?string $usWaId = null;
    
    /** El nombre del usuario de la app, dueño del este registro y de este token */
    #[ORM\Column(length: 50)]
    private ?string $usName = null;
    
    #[ORM\Column(length: 100)]
    private ?string $usEmail = null;

    /** El role del usuario de la app, dueño del este registro y de este token */
    #[ORM\Column(length: 15)]
    private ?string $role = null;
    
    /** El tema al cual esta suscrito */
    #[ORM\Column(length: 10)]
    private ?string $topic = null;
    
    /** El status, si esta activo en wats */
    #[ORM\Column]
    private ?int $stt = null;
    
    #[ORM\Column]
    /** La ultima ves que este registro se actualizo */
    private ?\DateTimeImmutable $lastAt = null;
    
    /** El tk de FB */
    #[ORM\Column(length: 255)]
    private ?string $tkfb = null;

    #[ORM\Column(length: 20)]
    private ?string $dev = null;

    #[ORM\Column(length: 50)]
    private ?string $usPlace = null;

    public function __construct()
    {
        $this->usWaId = '';
        $this->usName = '';
        $this->usEmail = '';
        $this->role = 'c';
        $this->topic = '';
        $this->stt = 0;
    }

    public function toJson() : array {
        return [
            'id'     => $this->id,
            'ownApp' => $this->ownApp,
            'usWaId' => $this->usWaId,
            'usName' => $this->usName,
            'usEmail'=> $this->usEmail,
            'role'   => $this->role,
            'topic'  => $this->topic,
            'stt'    => $this->stt,
            'lastAt' => $this->lastAt,
            'tkfb'   => $this->tkfb,
        ];
    }

    public function fromJson(array $data) : static {

        $this->dev = $data['dev'];
        $this->ownApp = $data['ownApp'];
        $this->usWaId = $data['usWaId'];
        $this->usName = $data['usName'];
        $this->usEmail = (array_key_exists('usMail', $data)) ? $data['usMail'] : '';
        $this->role = $data['role'];
        $this->lastAt = new \DateTimeImmutable("now");
        $this->tkfb = $data['tkfb'];
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnApp(): ?string
    {
        return $this->ownApp;
    }

    public function setOwnApp(string $ownApp): static
    {
        $this->ownApp = $ownApp;

        return $this;
    }

    public function getUsWaId(): ?string
    {
        return $this->usWaId;
    }

    public function setUsWaId(string $usWaId): static
    {
        $this->usWaId = $usWaId;

        return $this;
    }

    public function getUsName(): ?string
    {
        return $this->usName;
    }

    public function setUsName(string $usName): static
    {
        $this->usName = $usName;

        return $this;
    }

    public function getUsEmail(): ?string
    {
        return $this->usEmail;
    }

    public function setUsEmail(string $usEmail): static
    {
        $this->usEmail = $usEmail;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): static
    {
        $this->topic = $topic;

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

    public function getLastAt(): ?\DateTimeImmutable
    {
        return $this->lastAt;
    }

    public function setLastAt(\DateTimeImmutable $lastAt): static
    {
        $this->lastAt = $lastAt;

        return $this;
    }

    public function getTkfb(): ?string
    {
        return $this->tkfb;
    }

    public function setTkfb(string $tkfb): static
    {
        $this->tkfb = $tkfb;

        return $this;
    }

    public function getDev(): ?string
    {
        return $this->dev;
    }

    public function setDev(string $dev): static
    {
        $this->dev = $dev;

        return $this;
    }

    public function getUsPlace(): ?string
    {
        return $this->usPlace;
    }

    public function setUsPlace(string $usPlace): static
    {
        $this->usPlace = $usPlace;

        return $this;
    }

}
