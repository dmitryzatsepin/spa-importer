<?php

namespace Tests\Unit;

use App\Services\Bitrix24\Bitrix24BatchRequest;
use PHPUnit\Framework\TestCase;

class Bitrix24BatchRequestTest extends TestCase
{
    /**
     * Тест добавления команд в батч
     */
    public function test_can_add_commands_to_batch(): void
    {
        $batch = new Bitrix24BatchRequest();

        $batch->addCommand('cmd1', 'crm.deal.list', ['filter' => ['ID' => 1]]);
        $batch->addCommand('cmd2', 'crm.contact.list');

        $this->assertTrue($batch->hasCommands());
        $this->assertEquals(2, $batch->count());
    }

    /**
     * Тест разбиения батча на чанки при превышении лимита
     */
    public function test_splits_batch_into_chunks_when_exceeding_limit(): void
    {
        $batch = new Bitrix24BatchRequest();

        // Добавляем 75 команд (больше лимита 50)
        for ($i = 1; $i <= 75; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $this->assertEquals(75, $batch->count());
        $this->assertTrue($batch->needsSplitting());

        // Разбиваем на чанки
        $chunks = $batch->splitIntoChunks();

        $this->assertCount(2, $chunks); // 75 команд должны быть разбиты на 2 чанка
        $this->assertEquals(50, $chunks[0]->count()); // Первый чанк - 50 команд
        $this->assertEquals(25, $chunks[1]->count()); // Второй чанк - 25 команд

        // Проверяем, что ключи команд сохранены
        $allCommands = array_merge(
            array_keys($chunks[0]->getCommands()),
            array_keys($chunks[1]->getCommands())
        );

        $this->assertContains('cmd1', $allCommands);
        $this->assertContains('cmd50', $allCommands);
        $this->assertContains('cmd75', $allCommands);
    }

    /**
     * Тест что батч не разбивается если команд <= 50
     */
    public function test_does_not_split_batch_when_within_limit(): void
    {
        $batch = new Bitrix24BatchRequest();

        // Добавляем 50 команд (точно лимит)
        for ($i = 1; $i <= 50; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $this->assertEquals(50, $batch->count());
        $this->assertFalse($batch->needsSplitting());

        $chunks = $batch->splitIntoChunks();

        $this->assertCount(1, $chunks); // Не должно быть разбиения
        $this->assertSame($batch, $chunks[0]); // Возвращается тот же объект
    }

    /**
     * Тест разбиения с сохранением флага halt
     */
    public function test_preserves_halt_flag_in_chunks(): void
    {
        $batch = new Bitrix24BatchRequest();
        $batch->setHalt(true);

        for ($i = 1; $i <= 75; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $chunks = $batch->splitIntoChunks();

        foreach ($chunks as $chunk) {
            $this->assertTrue($chunk->getHalt(), 'Флаг halt должен сохраняться в чанках');
        }
    }

    /**
     * Тест корректного формирования строки команды с параметрами
     */
    public function test_formats_command_string_with_params(): void
    {
        $batch = new Bitrix24BatchRequest();

        $batch->addCommand('test', 'crm.deal.list', [
            'filter' => ['ID' => 123],
            'select' => ['ID', 'TITLE']
        ]);

        $commands = $batch->getCommands();

        $this->assertStringContainsString('crm.deal.list?', $commands['test']);
        $this->assertStringContainsString('filter', $commands['test']);
    }

    /**
     * Тест очистки батча
     */
    public function test_can_clear_batch(): void
    {
        $batch = new Bitrix24BatchRequest();
        $batch->setHalt(true);

        $batch->addCommand('cmd1', 'crm.deal.list');
        $batch->addCommand('cmd2', 'crm.contact.list');

        $this->assertTrue($batch->hasCommands());
        $this->assertTrue($batch->getHalt());

        $batch->clear();

        $this->assertFalse($batch->hasCommands());
        $this->assertFalse($batch->getHalt());
        $this->assertEquals(0, $batch->count());
    }

    /**
     * Тест разбиения большого количества команд на множество чанков
     */
    public function test_splits_large_batch_into_multiple_chunks(): void
    {
        $batch = new Bitrix24BatchRequest();

        // Добавляем 127 команд
        for ($i = 1; $i <= 127; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $chunks = $batch->splitIntoChunks();

        $this->assertCount(3, $chunks); // 127 команд = 3 чанка (50+50+27)
        $this->assertEquals(50, $chunks[0]->count());
        $this->assertEquals(50, $chunks[1]->count());
        $this->assertEquals(27, $chunks[2]->count());

        // Проверяем общее количество команд
        $totalCommands = array_sum(array_map(fn($chunk) => $chunk->count(), $chunks));
        $this->assertEquals(127, $totalCommands);
    }

    /**
     * Тест получения максимального размера батча
     */
    public function test_returns_max_batch_count(): void
    {
        $this->assertEquals(50, Bitrix24BatchRequest::getMaxBatchCount());
    }
}

