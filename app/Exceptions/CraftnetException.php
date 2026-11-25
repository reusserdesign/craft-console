<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * A failed Craftnet API request. Implementing the console exception
 * interface makes Symfony print just the message instead of a stack
 * trace, and the HTTP status lets callers recover from specific failures.
 */
class CraftnetException extends RuntimeException implements ExceptionInterface
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message, 1);
    }

    /**
     * Determine if the resource was missing or off-limits.
     */
    public function isNotAccessible(): bool
    {
        return in_array($this->status, [403, 404], true);
    }
}
