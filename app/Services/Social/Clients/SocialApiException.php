<?php

namespace App\Services\Social\Clients;

use RuntimeException;

class SocialApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 0)
    {
        parent::__construct($message, $statusCode);
    }

    public function isAuthenticationFailure(): bool
    {
        return in_array($this->statusCode, [401, 403], true);
    }
}
