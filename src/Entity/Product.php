<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(columns: ['token'], name: 'token_idx')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $uuid = null;

    #[ORM\Column(length: 10)]
    private ?string $src = null;

    #[ORM\Column(length: 190)]
    private ?string $title = null;

    #[ORM\Column(length: 190)]
    private ?string $token = null;

    #[ORM\Column(length: 190)]
    private ?string $permalink = null;

    #[ORM\Column(length: 190)]
    private ?string $thumbnail = null;

    #[ORM\Column(type: Types::JSON)]
    private array $fotos = [];

    #[ORM\Column(type: Types::TEXT)]
    private ?string $detalles = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?float $originalPrice = null;

    #[ORM\Column(length: 50)]
    private ?string $sellerId = null;

    #[ORM\Column(length: 150)]
    private ?string $sellerSlug = null;

    #[ORM\Column(type: Types::JSON)]
    private array $attrs = [];

    /**
     * 0 > Enviada a MLM
     * 1 > Esta activa
     * 2 > Enviada a MLM y ya Activa
     * 3 > Vendida por el dueño
     * 4 > Vendida por Anet
     * 5 > Cancelada | Eliminada
     */
    #[ORM\Column]
    private ?int $isVendida = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** */
    public function fromMap(array $prod) : static
    {
        $this->setUuid($prod['uuid']);
        $this->setSrc($prod['src']);
        $this->setTitle($prod['title']);
        $this->setToken($prod['token']);
        $this->setPermalink($prod['permalink']);
        $this->setThumbnail($prod['thumbnail']);
        $this->setFotos($prod['fotos']);
        $this->setDetalles($prod['detalles']);
        $this->setPrice($prod['price']);
        $this->setOriginalPrice($prod['originalPrice']);
        $this->setSellerId($prod['sellerId']);
        $this->setSellerSlug($prod['sellerSlug']);
        $this->setAttrs($prod['attrs']);
        $hoy = new \DateTimeImmutable('now');
        $this->setCreatedAt($hoy);
        $this->setUpdatedAt($hoy);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getPermalink(): ?string
    {
        return $this->permalink;
    }

    public function setPermalink(string $permalink): static
    {
        $this->permalink = $permalink;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getFotos(): array
    {
        return $this->fotos;
    }

    public function setFotos(array $fotos): static
    {
        $this->fotos = $fotos;

        return $this;
    }

    public function getDetalles(): ?string
    {
        return $this->detalles;
    }

    public function setDetalles(string $detalles): static
    {
        $this->detalles = $detalles;

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

    public function getOriginalPrice(): ?float
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(float $originalPrice): static
    {
        $this->originalPrice = $originalPrice;

        return $this;
    }

    public function getSellerId(): ?string
    {
        return $this->sellerId;
    }

    public function setSellerId(string $sellerId): static
    {
        $this->sellerId = $sellerId;

        return $this;
    }

    public function getSellerSlug(): ?string
    {
        return $this->sellerSlug;
    }

    public function setSellerSlug(string $sellerSlug): static
    {
        $this->sellerSlug = $sellerSlug;

        return $this;
    }

    public function getAttrs(): array
    {
        return $this->attrs;
    }

    public function setAttrs(array $attrs): static
    {
        $this->attrs = $attrs;

        return $this;
    }

    public function isIsVendida(): ?bool
    {
        return $this->isVendida;
    }

    public function setIsVendida(bool $isVendida): static
    {
        $this->isVendida = $isVendida;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
