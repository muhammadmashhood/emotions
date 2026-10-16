<?php

namespace App\Domain\Typeform\Exceptions;

use RuntimeException;

class TypeformException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $endpoint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
