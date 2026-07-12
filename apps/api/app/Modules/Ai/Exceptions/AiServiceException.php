<?php

namespace App\Modules\Ai\Exceptions;

use RuntimeException;

/**
 * Thrown when the external AI service returns a non-2xx response or is unreachable.
 * Caught by DispatchReceiptToAiService to trigger queue retries.
 */
class AiServiceException extends RuntimeException
{
    public function __construct(string $message = 'AI service request failed.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
