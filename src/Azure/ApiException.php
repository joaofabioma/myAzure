<?php

declare(strict_types=1);

namespace App\Azure;

/** Falha em chamada à API REST do Azure DevOps. */
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $responseBody = '',
    ) {
        parent::__construct($message, $statusCode);
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }
}
