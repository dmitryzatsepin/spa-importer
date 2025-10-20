<?php

namespace App\Services\Bitrix24\Exceptions;

use RuntimeException;

class Bitrix24APIException extends RuntimeException
{
    protected array $context = [];

    public function __construct(string $message = "", array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

