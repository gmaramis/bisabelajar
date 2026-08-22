<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when AI-assisted candidate generation fails (unavailable, timeout, malformed).
 */
final class ReassessmentGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureCode = 'generation_failed',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
