<?php

namespace App\Services\Bitrix24;

use InvalidArgumentException;

class Bitrix24BatchRequest
{
    protected const MAX_BATCH_COUNT = 50;

    protected array $commands = [];
    protected bool $halt = false;

    /**
     * Добавить команду в пакетный запрос
     */
    public function addCommand(string $key, string $method, array $params = []): self
    {
        if (count($this->commands) >= self::MAX_BATCH_COUNT) {
            throw new InvalidArgumentException(
                sprintf('Превышен максимальный размер пакетного запроса: %d', self::MAX_BATCH_COUNT)
            );
        }

        $commandString = $method;
        if (!empty($params)) {
            $commandString .= '?' . http_build_query($params);
        }

        $this->commands[$key] = $commandString;

        return $this;
    }

    /**
     * Установить флаг halt (останавливать выполнение при ошибке)
     */
    public function setHalt(bool $halt): self
    {
        $this->halt = $halt;
        return $this;
    }

    /**
     * Получить команды для отправки
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Получить значение флага halt
     */
    public function getHalt(): bool
    {
        return $this->halt;
    }

    /**
     * Проверить, есть ли команды
     */
    public function hasCommands(): bool
    {
        return !empty($this->commands);
    }

    /**
     * Очистить все команды
     */
    public function clear(): self
    {
        $this->commands = [];
        $this->halt = false;
        return $this;
    }

    /**
     * Получить количество команд
     */
    public function count(): int
    {
        return count($this->commands);
    }
}

