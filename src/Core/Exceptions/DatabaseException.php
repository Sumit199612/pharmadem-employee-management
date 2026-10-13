<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Class DatabaseException
 * Custom exception to prevent leaking sensitive credentials in error logs/traces.
 */
class DatabaseException extends RuntimeException
{
    public function __construct(string $message = "A database error occurred.", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
