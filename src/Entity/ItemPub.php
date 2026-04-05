<?php

namespace App\Entity;

use App\Repository\ItemPubRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemPubRepository::class)]
#[ORM\Table(name: 'item_pub', indexes: [
    new ORM\Index(
        name: 'idx_itempub_mrk_mdl_active_year',
        columns: ['mrk_id', 'mdl_id', 'is_active', 'anio_inicio', 'anio_fin']
    ),
    new ORM\Index(
        name: 'idx_itempub_mrk_mdl_active_created_id',
        columns: ['mrk_id', 'mdl_id', 'is_active', 'created', 'id']
    ),
    new ORM\Index(
        name: 'idx_itempub_mrk_mdl_lado_active',
        columns: ['mrk_id', 'mdl_id', 'is_active', 'lado', 'poss']
    ),
])]
class ItemPub
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $stt = null;

    #[ORM\Column(length: 150)]
    private ?string $idSrc = null;
    
    #[ORM\Column(length: 5)]
    private ?string $src = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $thumb = null;

    #[ORM\Column(length: 255)]
    private ?string $imgBig = null;

    #[ORM\Column]
    private ?float $price = null;
    
    #[ORM\Column(length: 255)]
    private ?string $link = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    private ?array $extras = null;
    
    #[ORM\Column]
    private ?\DateTimeImmutable $created = null;

    #[ORM\Column]
    private ?int $type = null;

    #[ORM\Column(length: 100)]
    private ?string $iku = null;

    #[ORM\Column]
    private ?float $costo = null;

    #[ORM\Column(length: 150)]
    private ?string $pieza = null;

    #[ORM\Column]
    private ?int $mrkId = null;

    #[ORM\Column]
    private ?int $mdlId = null;

    #[ORM\Column]
    private ?int $anioInicio = null;

    #[ORM\Column(nullable: true)]
    private ?int $anioFin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lado = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $poss = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $detalles = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantes = null;

    #[ORM\Column(length: 25)]
    private ?string $waId = null;

    #[ORM\Column]
    private ?int $taId = null;

    /** */
    public function __construct()
    {
			$this->stt = 1;
			$this->extras = [];
			$this->lado = 'A';
			$this->poss = 'A';
			$this->anioFin = 9999;
			$this->isActive = true;
			$this->created = new \DateTimeImmutable('now');
    }

    /** */
    public function fromJson(array $data): self 
    {
			$item = new self();
			$item->setStt((int) $data['stt']);
			$item->setType((int) $data['type']);
			$item->setIdSrc($data['idSrc'] ?? null);
			$item->setIku($data['iku'] ?? null);
			$item->setSrc($data['src'] ?? null);
			$item->setTitle($data['title'] ?? null);
			$item->setThumb($data['thumb'] ?? null);
			$item->setImgBig($data['imgBig'] ?? null);
			$item->setPrice((float) $data['price']);
			$item->setCosto((float) $data['costo']);
			$item->setLink($data['link'] ?? null);
			$item->setIsActive((bool) $data['isActive']);
			$item->setPieza($data['pieza'] ?? null);
			$item->setMrkId((int) $data['mrkId']);
			$item->setMdlId((int) $data['mdlId']);
			$item->setAnioInicio((int) $data['anioInicio']);
			$item->setAnioFin(isset($data['anioFin']) ? (int) $data['anioFin'] : null);
			$item->setLado($data['lado'] ?? null);
			$item->setPoss($data['poss'] ?? null);
			$item->setDetalles($data['detalles'] ?? null);
			$item->setWaId($data['waId'] ?? null);
			$item->setTaId((int) $data['taId']);

			// Extras viene como objeto JSON desde Dart
			$item->setExtras(
				isset($data['extras']) && is_array($data['extras'])
						? $data['extras']
						: null
			);

			// Fecha creada
			$item->setCreated(
				isset($data['created'])
						? new \DateTimeImmutable($data['created'])
						: new \DateTimeImmutable()
			);

			return $item;
    }
    
		/** */
		public function setPathImg(string $pathImg): void
		{
			$extras = $this->getExtras() ?? [];
			$extras['pathImg'] = $pathImg;
			$this->setExtras($extras);
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

    public function getStt(): ?int
    {
        return $this->stt;
    }

    public function setStt(int $stt): static
    {
        $this->stt = $stt;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIku(): ?string
    {
        return $this->iku;
    }

    public function setIku(string $iku): static
    {
        $this->iku = $iku;

        return $this;
    }

    public function getCosto(): ?float
    {
        return $this->costo;
    }

    public function setCosto(float $costo): static
    {
        $this->costo = $costo;

        return $this;
    }

    public function getPieza(): ?string
    {
        return $this->pieza;
    }

    public function setPieza(string $pieza): static
    {
        $this->pieza = $pieza;

        return $this;
    }

    public function getMrkId(): ?int
    {
        return $this->mrkId;
    }

    public function setMrkId(int $mrkId): static
    {
        $this->mrkId = $mrkId;

        return $this;
    }

    public function getMdlId(): ?int
    {
        return $this->mdlId;
    }

    public function setMdlId(int $mdlId): static
    {
        $this->mdlId = $mdlId;

        return $this;
    }

    public function getAnioInicio(): ?int
    {
        return $this->anioInicio;
    }

    public function setAnioInicio(int $anioInicio): static
    {
        $this->anioInicio = $anioInicio;

        return $this;
    }

    public function getAnioFin(): ?int
    {
        return $this->anioFin;
    }

    public function setAnioFin(?int $anioFin): static
    {
        $this->anioFin = $anioFin;

        return $this;
    }

    public function getLado(): ?string
    {
        return $this->lado;
    }

    public function setLado(?string $lado): static
    {
        $this->lado = $lado;

        return $this;
    }

    public function getPoss(): ?string
    {
        return $this->poss;
    }

    public function setPoss(?string $poss): static
    {
        $this->poss = $poss;

        return $this;
    }

    public function getDetalles(): ?string
    {
        return $this->detalles;
    }

    public function setDetalles(?string $detalles): static
    {
        $this->detalles = $detalles;

        return $this;
    }

    public function getVariantes(): ?string
    {
        return $this->variantes;
    }

    public function setVariantes(?string $variantes): static
    {
        $this->variantes = $variantes;

        return $this;
    }

    public function getWaId(): ?string
    {
        return $this->waId;
    }

    public function setWaId(string $waId): static
    {
        $this->waId = $waId;

        return $this;
    }

    public function getTaId(): ?int
    {
        return $this->taId;
    }

    public function setTaId(int $taId): static
    {
        $this->taId = $taId;

        return $this;
    }
}
