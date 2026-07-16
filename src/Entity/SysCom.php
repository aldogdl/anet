<?php

namespace App\Entity;

use App\Repository\SysComRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SysComRepository::class)]
class SysCom
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 50)]
	private ?string $slug = null;

	#[ORM\Column(length: 25)]
	private ?string $waId = null;

	#[ORM\Column(length: 50, nullable: true)]
	private ?string $taId = null;

	#[ORM\Column(length: 50)]
	private ?string $name = null;

	#[ORM\Column(length: 20)]
	private ?string $device = null;

	#[ORM\Column(length: 20)]
	private ?string $ip = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	private ?\DateTimeInterface $lastUpdate = null;
	
	#[ORM\Column(length: 255)]
	private ?string $fbtok = null;

	/** */
	public function __construct()
	{
		$this->lastUpdate = new \DateTimeImmutable('now');
	}
  
	/** */
	public function fromJson(array $data): static
	{
		$this->setSlug($data['slug']);
		$this->setWaId($data['waId']);
		$this->setDevice($data['device']);
		$this->setName($data['name']);
		$this->setIp($data['ip']);
		$this->setFbtok($data['fbtok']);
		$this->setTaId($data['taId']);
		$this->setLastUpdate(new \DateTimeImmutable('now'));
		return $this;
	}

	/** */
	public function toJson(): array {
		return [
			'slug'     => $this->slug,
			'waId'     => $this->waId,
			'taId'     => $this->taId,
			'name'     => $this->name,
			'device'   => $this->device,
			'ip'       => $this->ip,
			'fbtok'    => $this->fbtok,
			'lastUpdate' => $this->lastUpdate,
		];
	}

	public function getId(): ?int
	{
		return $this->id;
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

	public function getWaId(): ?string
	{
		return $this->waId;
	}

	public function setWaId(string $waId): static
	{
		$this->waId = $waId;

		return $this;
	}

	public function getTaId(): ?string
	{
		return $this->taId;
	}

	public function setTaId(?string $taId): static
	{
		$this->taId = $taId;

		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;

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

	public function getIp(): ?string
	{
		return $this->ip;
	}

	public function setIp(string $ip): static
	{
		$this->ip = $ip;

		return $this;
	}

	public function getLastUpdate(): ?\DateTimeInterface
	{
		return $this->lastUpdate;
	}

	public function setLastUpdate(\DateTimeInterface $lastUpdate): static
	{
		$this->lastUpdate = $lastUpdate;

		return $this;
	}

	public function getFbtok(): ?string
	{
		return $this->fbtok;
	}

	public function setFbtok(string $fbtok): static
	{
		$this->fbtok = $fbtok;

		return $this;
	}

}
