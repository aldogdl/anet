<?php

namespace App\Service\Security;

/**
 * Contexto inmutable de una sesión administrativa autenticada.
 */
class AdminAuthContext
{
    private string $userId;
    private string $companyId;
    private string $roleAdmin;
    private array $claims;
    private bool $isServerToken;

    public function __construct(
        string $userId,
        string $companyId,
        string $roleAdmin,
        array $claims = [],
        bool $isServerToken = false
    ) {
        $this->userId = $userId;
        $this->companyId = $companyId;
        $this->roleAdmin = $roleAdmin;
        $this->claims = $claims;
        $this->isServerToken = $isServerToken;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function getRoleAdmin(): string
    {
        return $this->roleAdmin;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function isServerToken(): bool
    {
        return $this->isServerToken;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleAdmin, $roles, true);
    }
}
