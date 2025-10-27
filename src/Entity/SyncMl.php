<?php

namespace App\Entity;

use App\Repository\SyncMlRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SyncMlRepository::class)]
class SyncMl
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 50)]
	private ?string $msg_id = null;

	#[ORM\Column(length: 50)]
	private ?string $topic = null;

	#[ORM\Column(length: 70)]
	private ?string $resource = null;

	#[ORM\Column]
	private ?int $user_id = null;

	#[ORM\Column]
	private ?\DateTimeImmutable $sendAt = null;

	#[ORM\Column]
	private ?\DateTimeImmutable $receivedAt = null;

	/** */
	public function set(array $msg) {

		$this->msg_id   = $msg['_id'];
		$this->topic    = $msg['topic'];
		$this->resource = $msg['resource'];
		$this->user_id  = $msg['user_id'];
		$this->sendAt   = new \DateTimeImmutable($msg['sent']);
		$this->receivedAt = new \DateTimeImmutable($msg['received']);
		return $this;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getMsgId(): ?string
	{
		return $this->msg_id;
	}

	public function setMsgId(string $msg_id): static
	{
		$this->msg_id = $msg_id;

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

	public function getResource(): ?string
	{
		return $this->resource;
	}

	public function setResource(string $resource): static
	{
		$this->resource = $resource;

		return $this;
	}

	public function getUserId(): ?int
	{
		return $this->user_id;
	}

	public function setUserId(int $user_id): static
	{
		$this->user_id = $user_id;

		return $this;
	}

	public function getSendAt(): ?\DateTimeImmutable
	{
		return $this->sendAt;
	}

	public function setSendAt(\DateTimeImmutable $sendAt): static
	{
		$this->sendAt = $sendAt;

		return $this;
	}

	public function getReceivedAt(): ?\DateTimeImmutable
	{
		return $this->receivedAt;
	}

	public function setReceivedAt(\DateTimeImmutable $receivedAt): static
	{
		$this->receivedAt = $receivedAt;

		return $this;
	}
}
