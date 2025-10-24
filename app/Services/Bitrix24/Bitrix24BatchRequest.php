<?php

namespace App\Services\Bitrix24;


class Bitrix24BatchRequest
{
    protected const MAX_BATCH_COUNT = 50;

    protected array $commands = [];
    protected bool $halt = false;

    /**
     * Добавить команду в пакетный запрос
     * Больше не выбрасывает исключение при превышении лимита - вместо этого
     * используйте метод splitIntoChunks() для разбиения на несколько батчей
     */
    public function addCommand(string $key, string $method, array $params = []): self
    {
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

    /**
     * Разбить текущие команды на несколько батчей по MAX_BATCH_COUNT команд
     * 
     * @return array<int, Bitrix24BatchRequest> Массив объектов BatchRequest
     */
    public function splitIntoChunks(): array
    {
        if ($this->count() <= self::MAX_BATCH_COUNT) {
            return [$this];
        }

        $chunks = [];
        $commandChunks = array_chunk($this->commands, self::MAX_BATCH_COUNT, true);

        foreach ($commandChunks as $commandChunk) {
            $batchRequest = new self();
            $batchRequest->commands = $commandChunk;
            $batchRequest->halt = $this->halt;
            $chunks[] = $batchRequest;
        }

        return $chunks;
    }

    /**
     * Получить максимально допустимое количество команд в одном батче
     */
    public static function getMaxBatchCount(): int
    {
        return self::MAX_BATCH_COUNT;
    }

    /**
     * Проверить, нужно ли разбивать батч на несколько запросов
     */
    public function needsSplitting(): bool
    {
        return $this->count() > self::MAX_BATCH_COUNT;
    }
}

