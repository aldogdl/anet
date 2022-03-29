<?php

namespace App\Entity;

use App\Repository\NG2ContactosRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: NG2ContactosRepository::class)]
class NG2Contactos implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private $curc;

    #[ORM\Column(type: 'json')]
    private $roles = [];

    #[ORM\Column(type: 'string')]
    private $password;

    #[ORM\ManyToOne(targetEntity: NG1Empresas::class, inversedBy: 'contactos')]
    #[ORM\JoinColumn(nullable: false)]
    private $empresa;

    #[ORM\Column(type: 'string', length: 100)]
    private $nombre;

    #[ORM\Column(type: 'boolean')]
    private $isCot;

    #[ORM\Column(type: 'string', length: 50)]
    private $cargo;

    #[ORM\Column(type: 'string', length: 15)]
    private $celular;

    #[ORM\Column(type: 'text')]
    private $keyCel;

    #[ORM\Column(type: 'text')]
    private $keyWeb;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurc(): ?string
    {
        return $this->curc;
    }

    public function setCurc(string $curc): self
    {
        $this->curc = $curc;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->curc;
    }

    /**
     * Metodo para crear el token.
     */
    public function getUsername(): string {
        return $this->getUserIdentifier();
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getIsCot(): ?bool
    {
        return $this->isCot;
    }

    public function setIsCot(bool $isCot): self
    {
        $this->isCot = $isCot;

        return $this;
    }

    public function getCargo(): ?string
    {
        return $this->cargo;
    }

    public function setCargo(string $cargo): self
    {
        $this->cargo = $cargo;

        return $this;
    }

    public function getCelular(): ?string
    {
        return $this->celular;
    }

    public function setCelular(string $celular): self
    {
        $this->celular = $celular;

        return $this;
    }

    public function getKeyCel(): ?string
    {
        return $this->keyCel;
    }

    public function setKeyCel(string $keyCel): self
    {
        $this->keyCel = $keyCel;

        return $this;
    }

    public function getKeyWeb(): ?string
    {
        return $this->keyWeb;
    }

    public function setKeyWeb(string $keyWeb): self
    {
        $this->keyWeb = $keyWeb;

        return $this;
    }

    public function getEmpresa(): ?NG1Empresas
    {
        return $this->empresa;
    }

    public function setEmpresa(?NG1Empresas $empresa): self
    {
        $this->empresa = $empresa;

        return $this;
    }

}
