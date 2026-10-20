<?php

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\Response;

class AdminAuthException extends \Exception
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = Response::HTTP_UNAUTHORIZED, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
