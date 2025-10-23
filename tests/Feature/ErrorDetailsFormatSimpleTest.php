<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\Portal;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorDetailsFormatSimpleTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_details_unified_format()
    {
        // Создаем портал
        $portal = Portal::factory()->create([
            'member_id' => 'test_member_123',
            'domain' => 'test.bitrix24.ru',
        ]);

        // Создаем задачу с ошибками в новом формате
        $errorDetails = [
            [
                'row' => 5,
                'error' => 'Ошибка валидации данных',
                'data' => ['field' => 'email', 'value' => 'invalid-email'],
            ],
            [
                'row' => 8,
                'error' => 'Дубликат записи',
            ],
            [
                'error' => 'Системная ошибка',
                'data' => ['file' => 'ProcessImportJob.php', 'line' => 123],
            ],
        ];

        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'status' => 'failed',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 8,
            'error_details' => $errorDetails,
        ]);

        // Проверяем, что данные сохранились правильно
        $this->assertDatabaseHas('import_jobs', [
            'id' => $importJob->id,
            'status' => 'failed',
        ]);

        // Проверяем API ответ
        $response = $this->getJson("/api/v1/import/{$importJob->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'job_id' => $importJob->id,
                    'status' => 'failed',
                    'error_details' => $errorDetails,
                ],
            ]);

        // Проверяем структуру каждого элемента ошибки
        $responseData = $response->json('data.error_details');
        $this->assertCount(3, $responseData);

        // Первая ошибка с номером строки и данными
        $this->assertEquals(5, $responseData[0]['row']);
        $this->assertEquals('Ошибка валидации данных', $responseData[0]['error']);
        $this->assertArrayHasKey('data', $responseData[0]);
        $this->assertEquals('email', $responseData[0]['data']['field']);

        // Вторая ошибка только с номером строки
        $this->assertEquals(8, $responseData[1]['row']);
        $this->assertEquals('Дубликат записи', $responseData[1]['error']);
        $this->assertArrayNotHasKey('data', $responseData[1]);

        // Третья ошибка только с данными
        $this->assertArrayNotHasKey('row', $responseData[2]);
        $this->assertEquals('Системная ошибка', $responseData[2]['error']);
        $this->assertArrayHasKey('data', $responseData[2]);
    }

    public function test_error_details_null_format()
    {
        // Создаем портал
        $portal = Portal::factory()->create([
            'member_id' => 'test_member_456',
            'domain' => 'test2.bitrix24.ru',
        ]);

        // Создаем задачу без ошибок
        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'status' => 'completed',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 10,
            'error_details' => null,
        ]);

        // Проверяем API ответ
        $response = $this->getJson("/api/v1/import/{$importJob->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'job_id' => $importJob->id,
                    'status' => 'completed',
                    'error_details' => null,
                ],
            ]);
    }
}
