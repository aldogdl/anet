<?php

namespace App\Entity;

use App\Repository\ItemPubRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemPubRepository::class)]
class ItemPub
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $thumb = null;

    #[ORM\Column(length: 255)]
    private ?string $imgBig = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(length: 50)]
    private ?string $place = null;

    #[ORM\Column(length: 255)]
    private ?string $link = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created = null;

    #[ORM\Column(nullable: true)]
    private ?array $extras = null;

    #[ORM\Column(length: 150)]
    private ?string $idSrc = null;

    #[ORM\Column(length: 5)]
    private ?string $src = null;

    #[ORM\Column(length: 25)]
    private ?string $ownSlug = null;

    #[ORM\Column(length: 20)]
    private ?string $ownWaId = null;

    #[ORM\Column]
    private ?int $stt = null;

    /** */
    public function __construct()
    {
        $this->stt = 0;
        $this->extras = [];
        $this->created = new \DateTimeImmutable('now');
    }

    /** */
    public function toSlim() : array {
        $slim = [
            'tk' => $this->extras['tk'],
            'pz' => $this->extras['pz'],
            'mk' => $this->extras['mk'],
            'md' => $this->extras['md'],
            'a' => $this->extras['a'],
            'pr' => $this->price,
        ];
        if(array_key_exists('l', $this->extras)) {
            $slim['l'] = $this->extras['l'];
        }
        if(array_key_exists('p', $this->extras)) {
            $slim['p'] = $this->extras['p'];
        }
        $slim['im'] = $this->imgBig;
        $slim['os'] = $this->ownSlug;
        $slim['ow'] = $this->ownWaId;
        $slim['s'] = $this->idSrc;
        $slim['sr'] = $this->src;
        return $slim;
    }

    /** */
    public function fromJson(array $json): static 
    {
        $date = new \DateTimeImmutable($json['created'], new \DateTimeZone("America/Mexico_City"));  
        $this->title = $json['title'];
        $this->thumb = $json['thumb'];
        $this->imgBig = $json['imgBig'];
        $this->price = $json['price'];
        $this->place = $json['place'];
        $this->link = $json['link'];
        $this->isActive = $json['isActive'];
        $this->created = $date;
        $this->extras = (array_key_exists('extras', $json)) ? $json['extras'] : [];
        $this->idSrc = $json['idSrc'];
        $this->src = $json['src'];
        $this->ownSlug = $json['ownSlug'];
        $this->ownWaId = $json['ownWaId'];
        $this->stt = $json['stt'];
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getThumb(): ?string
    {
        return $this->thumb;
    }

    public function setThumb(string $thumb): static
    {
        $this->thumb = $thumb;

        return $this;
    }

    public function getImgBig(): ?string
    {
        return $this->imgBig;
    }

    public function setImgBig(string $imgBig): static
    {
        $this->imgBig = $imgBig;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(string $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreated(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(\DateTimeImmutable $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getExtras(): ?array
    {
        return $this->extras;
    }

    public function setExtras(?array $extras): static
    {
        $this->extras = $extras;

        return $this;
    }

    public function getIdSrc(): ?string
    {
        return $this->idSrc;
    }

    public function setIdSrc(string $idSrc): static
    {
        $this->idSrc = $idSrc;

        return $this;
    }

    public function getSrc(): ?string
    {
        return $this->src;
    }

    public function setSrc(string $src): static
    {
        $this->src = $src;

        return $this;
    }

    public function getOwnSlug(): ?string
    {
        return $this->ownSlug;
    }

    public function setOwnSlug(string $ownSlug): static
    {
        $this->ownSlug = $ownSlug;

        return $this;
    }

    public function getOwnWaId(): ?string
    {
        return $this->ownWaId;
    }

    public function setOwnWaId(string $ownWaId): static
    {
        $this->ownWaId = $ownWaId;

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
}
