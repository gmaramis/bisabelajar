<?php

namespace App\Exceptions\Ai;

use RuntimeException;

class AiClientException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $failureCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
