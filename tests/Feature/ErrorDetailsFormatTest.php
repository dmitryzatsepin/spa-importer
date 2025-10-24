<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\Portal;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorDetailsFormatTest extends TestCase
{
    use RefreshDatabase;

    protected Portal $portal;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем базу данных
        ImportJob::truncate();
        Portal::truncate();

        // Создаем тестовый портал
        $this->portal = Portal::create([
            'member_id' => 'test_member_123',
            'domain' => 'test.bitrix24.ru',
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh_token',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_import_status_without_errors_returns_null_error_details()
    {
        $importJob = ImportJob::create([
            'portal_id' => $this->portal->id,
            'status' => 'completed',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 10,
            'error_details' => null,
        ]);

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

    public function test_import_status_with_errors_returns_unified_format()
    {
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
            'portal_id' => $this->portal->id,
            'status' => 'failed',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 8,
            'error_details' => $errorDetails,
        ]);

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

    public function test_history_endpoint_handles_error_details_correctly()
    {
        // Создаем задачи с разными статусами
        $completedJob = ImportJob::create([
            'portal_id' => $this->portal->id,
            'status' => 'completed',
            'original_filename' => 'completed.csv',
            'stored_filepath' => 'completed.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 5,
            'processed_rows' => 5,
            'error_details' => null,
        ]);

        $failedJob = ImportJob::create([
            'portal_id' => $this->portal->id,
            'status' => 'failed',
            'original_filename' => 'failed.csv',
            'stored_filepath' => 'failed.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 3,
            'processed_rows' => 2,
            'error_details' => [
                ['row' => 2, 'error' => 'Ошибка валидации'],
                ['error' => 'Системная ошибка', 'data' => ['code' => 500]],
            ],
        ]);

        $response = $this->getJson("/api/v1/import/history?portal_id={$this->portal->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $historyData = $response->json('data');
        $this->assertCount(2, $historyData);

        // Проверяем completed задачу
        $completedItem = collect($historyData)->firstWhere('job_id', $completedJob->id);
        $this->assertFalse($completedItem['has_errors']);
        $this->assertEquals(0, $completedItem['error_count']);

        // Проверяем failed задачу
        $failedItem = collect($historyData)->firstWhere('job_id', $failedJob->id);
        $this->assertTrue($failedItem['has_errors']);
        $this->assertEquals(2, $failedItem['error_count']);
    }

    public function test_error_log_download_uses_unified_format()
    {
        $errorDetails = [
            [
                'row' => 3,
                'error' => 'Ошибка валидации email',
                'data' => ['email' => 'invalid@'],
            ],
            [
                'row' => 7,
                'error' => 'Дубликат записи',
            ],
        ];

        $importJob = ImportJob::create([
            'portal_id' => $this->portal->id,
            'status' => 'failed',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 7,
            'error_details' => $errorDetails,
        ]);

        $response = $this->get("/api/v1/import/{$importJob->id}/error-log");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csvContent = $response->getContent();

        // Проверяем, что CSV содержит правильные данные
        $this->assertStringContainsString('Номер строки', $csvContent);
        $this->assertStringContainsString('Ошибка', $csvContent);
        $this->assertStringContainsString('Исходные данные', $csvContent);
        $this->assertStringContainsString('3', $csvContent);
        $this->assertStringContainsString('Ошибка валидации email', $csvContent);
        $this->assertStringContainsString('7', $csvContent);
        $this->assertStringContainsString('Дубликат записи', $csvContent);
    }
}
