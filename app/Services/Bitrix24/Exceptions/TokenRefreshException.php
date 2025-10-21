<?php

namespace App\Services\Bitrix24\Exceptions;

class TokenRefreshException extends Bitrix24APIException
{
    public function __construct(string $message = 'Не удалось обновить токен доступа', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $context, $code, $previous);
    }
}

