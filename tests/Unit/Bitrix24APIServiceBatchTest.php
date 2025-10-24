<?php

namespace Tests\Unit;

use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Bitrix24APIServiceBatchTest extends TestCase
{
    /**
     * Тест успешного выполнения батча с менее чем 50 командами
     */
    public function test_executes_single_batch_successfully(): void
    {
        // Мокируем успешный ответ от Bitrix24
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::response([
                'result' => [
                    'result' => [
                        'cmd1' => ['ID' => 1, 'TITLE' => 'Deal 1'],
                        'cmd2' => ['ID' => 2, 'TITLE' => 'Deal 2'],
                    ],
                    'result_error' => [],
                    'result_time' => [
                        'cmd1' => 0.1,
                        'cmd2' => 0.15,
                    ],
                    'result_total' => [
                        'cmd1' => 1,
                        'cmd2' => 1,
                    ],
                ],
                'time' => [
                    'start' => 1234567890,
                    'finish' => 1234567891,
                    'duration' => 1,
                ],
            ], 200),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();
        $batch->addCommand('cmd1', 'crm.deal.get', ['id' => 1]);
        $batch->addCommand('cmd2', 'crm.deal.get', ['id' => 2]);

        $result = $service->callBatch($batch);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('cmd1', $result['results']);
        $this->assertArrayHasKey('cmd2', $result['results']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(['ID' => 1, 'TITLE' => 'Deal 1'], $result['results']['cmd1']['result']);
    }

    /**
     * Тест обработки ошибок отдельных команд в батче
     */
    public function test_handles_individual_command_errors_in_batch(): void
    {
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::response([
                'result' => [
                    'result' => [
                        'cmd1' => ['ID' => 1, 'TITLE' => 'Deal 1'],
                        'cmd2' => null, // Команда с ошибкой
                    ],
                    'result_error' => [
                        'cmd2' => [
                            'error' => 'NOT_FOUND',
                            'error_description' => 'Элемент не найден',
                        ],
                    ],
                    'result_time' => [
                        'cmd1' => 0.1,
                        'cmd2' => 0.05,
                    ],
                    'result_total' => [
                        'cmd1' => 1,
                        'cmd2' => 0,
                    ],
                ],
                'time' => [
                    'duration' => 0.5,
                ],
            ], 200),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();
        $batch->addCommand('cmd1', 'crm.deal.get', ['id' => 1]);
        $batch->addCommand('cmd2', 'crm.deal.get', ['id' => 999]);

        $result = $service->callBatch($batch);

        // Проверяем, что есть информация об ошибках
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('errors_count', $result);
        $this->assertEquals(1, $result['errors_count']);

        // Проверяем детали ошибки
        $this->assertArrayHasKey('cmd2', $result['errors']);
        $this->assertEquals('cmd2', $result['errors']['cmd2']['command_key']);
        $this->assertArrayHasKey('error', $result['errors']['cmd2']);
    }

    /**
     * Тест автоматического разбиения батча >50 команд
     */
    public function test_automatically_splits_large_batch(): void
    {
        // Мокируем два последовательных запроса для двух чанков
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::sequence()
                ->push([
                    'result' => [
                        'result' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            ['success' => true]
                        ),
                        'result_error' => [],
                        'result_time' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            0.1
                        ),
                        'result_total' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            1
                        ),
                    ],
                    'time' => ['duration' => 2.5],
                ], 200)
                ->push([
                    'result' => [
                        'result' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(51, 75)),
                            ['success' => true]
                        ),
                        'result_error' => [],
                        'result_time' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(51, 75)),
                            0.1
                        ),
                        'result_total' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(51, 75)),
                            1
                        ),
                    ],
                    'time' => ['duration' => 1.5],
                ], 200),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();

        // Добавляем 75 команд
        for ($i = 1; $i <= 75; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $result = $service->callBatch($batch);

        // Проверяем, что все результаты собраны
        $this->assertEquals(75, $result['total']);
        $this->assertArrayHasKey('chunks_executed', $result);
        $this->assertArrayHasKey('chunks_total', $result);
        $this->assertEquals(2, $result['chunks_total']);
        $this->assertEquals(2, $result['chunks_executed']);

        // Проверяем, что все ключи команд присутствуют
        $this->assertArrayHasKey('cmd1', $result['results']);
        $this->assertArrayHasKey('cmd50', $result['results']);
        $this->assertArrayHasKey('cmd75', $result['results']);
    }

    /**
     * Тест retry-политики при временных ошибках
     */
    public function test_retries_on_temporary_errors(): void
    {
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::sequence()
                ->push('Server Error', 500) // Первая попытка - ошибка
                ->push('Server Error', 500) // Вторая попытка - ошибка
                ->push([ // Третья попытка - успех
                    'result' => [
                        'result' => [
                            'cmd1' => ['ID' => 1],
                        ],
                        'result_error' => [],
                        'result_time' => ['cmd1' => 0.1],
                        'result_total' => ['cmd1' => 1],
                    ],
                    'time' => ['duration' => 0.5],
                ], 200),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();
        $batch->addCommand('cmd1', 'crm.deal.get', ['id' => 1]);

        // Выполняем с 2 retry
        $result = $service->callBatch($batch, maxRetries: 2);

        $this->assertArrayHasKey('results', $result);
        $this->assertEquals(['ID' => 1], $result['results']['cmd1']['result']);

        // Проверяем, что было сделано 3 запроса (1 + 2 retry)
        Http::assertSentCount(3);
    }

    /**
     * Тест прерывания выполнения при halt=true
     */
    public function test_halts_execution_on_chunk_error_with_halt_flag(): void
    {
        // Мокируем первый чанк с успехом, второй с ошибкой
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::sequence()
                ->push([
                    'result' => [
                        'result' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            ['success' => true]
                        ),
                        'result_error' => [],
                        'result_time' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            0.1
                        ),
                        'result_total' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            1
                        ),
                    ],
                    'time' => ['duration' => 2.5],
                ], 200)
                ->push([
                    'error' => 'INTERNAL_ERROR',
                    'error_description' => 'Internal server error',
                ], 500),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();
        $batch->setHalt(true); // Устанавливаем halt

        for ($i = 1; $i <= 75; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $this->expectException(Bitrix24APIException::class);
        $this->expectExceptionMessageMatches('/Выполнение прервано на батче/');

        $service->callBatch($batch);
    }

    /**
     * Тест продолжения выполнения при halt=false и ошибке в чанке
     */
    public function test_continues_execution_on_chunk_error_without_halt_flag(): void
    {
        Http::fake([
            'test.bitrix24.ru/rest/batch.json' => Http::sequence()
                ->push([
                    'result' => [
                        'result' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            ['success' => true]
                        ),
                        'result_error' => [],
                        'result_time' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            0.1
                        ),
                        'result_total' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(1, 50)),
                            1
                        ),
                    ],
                    'time' => ['duration' => 2.5],
                ], 200)
                ->push([
                    'error' => 'INTERNAL_ERROR',
                    'error_description' => 'Internal server error',
                ], 500)
                ->push([
                    'result' => [
                        'result' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(101, 120)),
                            ['success' => true]
                        ),
                        'result_error' => [],
                        'result_time' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(101, 120)),
                            0.1
                        ),
                        'result_total' => array_fill_keys(
                            array_map(fn($i) => "cmd{$i}", range(101, 120)),
                            1
                        ),
                    ],
                    'time' => ['duration' => 1.2],
                ], 200),
        ]);

        $service = new Bitrix24APIService('test.bitrix24.ru', 'test_token');

        $batch = new Bitrix24BatchRequest();
        $batch->setHalt(false); // Не прерываем выполнение

        // Добавляем 120 команд (3 чанка)
        for ($i = 1; $i <= 120; $i++) {
            $batch->addCommand("cmd{$i}", 'crm.deal.get', ['id' => $i]);
        }

        $result = $service->callBatch($batch);

        // Проверяем, что выполнилось 2 из 3 чанков (второй упал, но продолжили)
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('chunk_2', $result['errors']);
        $this->assertEquals(2, $result['chunks_executed']); // Только 1 и 3 чанки успешны
        $this->assertEquals(3, $result['chunks_total']);
    }
}

