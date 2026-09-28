<?php

namespace App\Base\Exceptions;

use Exception;

/**
 * Business/domain rule violation → HTTP 422 with stable error_code for clients.
 */
class DomainException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_error',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
