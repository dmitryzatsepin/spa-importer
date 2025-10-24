<?php

namespace Tests\Unit;

use App\Models\ImportJob;
use App\Models\Portal;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorDetailsFormatUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_job_saves_error_details_in_unified_format()
    {
        // Создаем портал
        $portal = Portal::factory()->create();

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

        // Перезагружаем модель из базы
        $importJob->refresh();

        // Проверяем, что error_details сохранились в правильном формате
        $this->assertIsArray($importJob->error_details);
        $this->assertCount(3, $importJob->error_details);

        // Проверяем структуру каждого элемента ошибки
        $this->assertEquals(5, $importJob->error_details[0]['row']);
        $this->assertEquals('Ошибка валидации данных', $importJob->error_details[0]['error']);
        $this->assertArrayHasKey('data', $importJob->error_details[0]);
        $this->assertEquals('email', $importJob->error_details[0]['data']['field']);

        $this->assertEquals(8, $importJob->error_details[1]['row']);
        $this->assertEquals('Дубликат записи', $importJob->error_details[1]['error']);
        $this->assertArrayNotHasKey('data', $importJob->error_details[1]);

        $this->assertArrayNotHasKey('row', $importJob->error_details[2]);
        $this->assertEquals('Системная ошибка', $importJob->error_details[2]['error']);
        $this->assertArrayHasKey('data', $importJob->error_details[2]);
    }

    public function test_import_job_saves_null_error_details()
    {
        // Создаем портал
        $portal = Portal::factory()->create();

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

        // Перезагружаем модель из базы
        $importJob->refresh();

        // Проверяем, что error_details null
        $this->assertNull($importJob->error_details);
    }

    public function test_mark_as_failed_uses_unified_format()
    {
        // Создаем портал
        $portal = Portal::factory()->create();

        // Создаем задачу
        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'status' => 'processing',
            'original_filename' => 'test.csv',
            'stored_filepath' => 'test.csv',
            'field_mappings' => [],
            'settings' => [],
            'total_rows' => 10,
            'processed_rows' => 5,
        ]);

        // Используем метод markAsFailed с новым форматом
        $errorDetails = [
            [
                'error' => 'Системная ошибка',
                'data' => ['file' => 'ProcessImportJob.php', 'line' => 123],
            ],
        ];

        $importJob->markAsFailed($errorDetails);

        // Проверяем результат
        $importJob->refresh();
        $this->assertEquals('failed', $importJob->status);
        $this->assertIsArray($importJob->error_details);
        $this->assertCount(1, $importJob->error_details);
        $this->assertEquals('Системная ошибка', $importJob->error_details[0]['error']);
        $this->assertArrayHasKey('data', $importJob->error_details[0]);
    }
}
