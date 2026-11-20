<?php

namespace App\Services\Ai;

/**
 * Raised when the AI provider returns an error or an unreadable response.
 *
 * Deliberately not a RuntimeException: callers treat RuntimeException from
 * AiGenerationService::forUser() as "AI is not configured" (HTTP 422).
 */
class AiProviderException extends \Exception
{
    public function __construct(string $message, public readonly ?int $status = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
