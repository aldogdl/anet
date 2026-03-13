<?php

namespace App\Entity;

use App\Repository\MMEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MMEntityRepository::class)]
class MMEntity
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 50)]
	private ?string $name = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $variants = null;

	#[ORM\Column(nullable: true)]
	private ?int $idMrk = null;

	#[ORM\Column(nullable: true)]
	private ?array $scrape = null;

	#[ORM\Column(nullable: true)]
	private ?array $extras = null;

    /** */
	public function __construct() {
		$this->idMrk = 0;
	}

	public function getId(): ?int
	{
		return $this->id;
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

	public function getVariants(): ?array
	{
		return $this->variants;
	}

	public function setVariants(?array $variants): static
	{
		$this->variants = $variants;

		return $this;
	}

	public function getIdMrk(): ?int
	{
		return $this->idMrk;
	}

	public function setIdMrk(?int $idMrk): static
	{
		$this->idMrk = $idMrk;

		return $this;
	}

	public function getScrape(): ?array
	{
		return $this->scrape;
	}

	public function setScrape(?array $scrape): static
	{
		$this->scrape = $scrape;

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

	/** */
	public function fromJson(array $json): static
	{
		$this->id = $json['id'];
		$this->name = $json['name'];
		$this->idMrk = $json['id_mrk'];
		$this->variants = $json['variants'];
		$this->scrape = (array_key_exists('scrape', $json)) ? $json['scrape'] : [];
		$this->extras = $json['extras'];
		if($this->idMrk == null) {
			$this->idMrk = 0; // por defecto, si no se especifica, se asume que es una marca
		}
		return $this;
	}


}
