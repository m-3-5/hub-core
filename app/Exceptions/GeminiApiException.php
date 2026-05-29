<?php

namespace App\Exceptions;

use Exception;

class GeminiApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly bool $quotaExceeded = false,
    ) {
        parent::__construct($message);
    }
}
