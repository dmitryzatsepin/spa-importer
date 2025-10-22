<?php

namespace Tests\Unit;

use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use App\Services\Bitrix24\Bitrix24APIService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProcessImportJobTest extends TestCase
{
    /**
     * Тест: transformRowToFields работает с новым форматом (source/target)
     */
    public function test_transform_row_to_fields_with_new_format()
    {
        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('transformRowToFields');
        $method->setAccessible(true);

        $row = ['John Doe', 'john@example.com', '123456'];
        $headers = ['Name', 'Email', 'Phone'];
        $fieldMappings = [
            ['source' => 'Name', 'target' => 'TITLE'],
            ['source' => 'Email', 'target' => 'UF_EMAIL'],
            ['source' => 'Phone', 'target' => 'UF_PHONE'],
        ];

        $result = $method->invoke($job, $row, $headers, $fieldMappings);

        $this->assertEquals([
            'TITLE' => 'John Doe',
            'UF_EMAIL' => 'john@example.com',
            'UF_PHONE' => '123456',
        ], $result);
    }

    /**
     * Тест: transformRowToFields работает со старым форматом (source_column/target_field)
     */
    public function test_transform_row_to_fields_with_old_format()
    {
        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('transformRowToFields');
        $method->setAccessible(true);

        $row = ['Jane Smith', 'jane@example.com'];
        $headers = ['Name', 'Email'];
        $fieldMappings = [
            ['source_column' => 'Name', 'target_field' => 'TITLE'],
            ['source_column' => 'Email', 'target_field' => 'UF_EMAIL'],
        ];

        $result = $method->invoke($job, $row, $headers, $fieldMappings);

        $this->assertEquals([
            'TITLE' => 'Jane Smith',
            'UF_EMAIL' => 'jane@example.com',
        ], $result);
    }

    /**
     * Тест: transformRowToFields fallback со старого на новый формат
     */
    public function test_transform_row_to_fields_fallback()
    {
        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('transformRowToFields');
        $method->setAccessible(true);

        $row = ['Test User', 'test@test.com'];
        $headers = ['Name', 'Email'];
        $fieldMappings = [
            // Новый формат имеет приоритет
            ['source' => 'Name', 'source_column' => 'OldName', 'target' => 'TITLE', 'target_field' => 'OLD_TITLE'],
            // Только старый формат
            ['source_column' => 'Email', 'target_field' => 'UF_EMAIL'],
        ];

        $result = $method->invoke($job, $row, $headers, $fieldMappings);

        $this->assertEquals([
            'TITLE' => 'Test User',  // новый формат
            'UF_EMAIL' => 'test@test.com',  // старый формат
        ], $result);
    }

    /**
     * Тест: isDuplicate работает с новым форматом (duplicate_field)
     */
    public function test_is_duplicate_with_new_format()
    {
        $importJob = new ImportJob();
        $importJob->settings = ['duplicate_field' => 'UF_EMAIL'];

        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('isDuplicate');
        $method->setAccessible(true);

        $fields = ['TITLE' => 'Test', 'UF_EMAIL' => 'test@test.com'];

        // Mock API service
        $apiService = $this->createMock(Bitrix24APIService::class);
        $apiService->method('call')->willReturn(['result' => []]);

        $result = $method->invoke($job, $fields, 123, $apiService, $importJob);

        $this->assertFalse($result);  // Нет дубликатов
    }

    /**
     * Тест: isDuplicate работает со старым форматом (duplicate_check_field)
     */
    public function test_is_duplicate_with_old_format()
    {
        $importJob = new ImportJob();
        $importJob->settings = ['duplicate_check_field' => 'UF_EMAIL'];

        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('isDuplicate');
        $method->setAccessible(true);

        $fields = ['TITLE' => 'Test', 'UF_EMAIL' => 'test@test.com'];

        // Mock API service
        $apiService = $this->createMock(Bitrix24APIService::class);
        $apiService->method('call')->willReturn(['result' => [['ID' => 1]]]);

        $result = $method->invoke($job, $fields, 123, $apiService, $importJob);

        $this->assertTrue($result);  // Найден дубликат
    }

    /**
     * Тест: isDuplicate приоритет нового формата над старым
     */
    public function test_is_duplicate_priority_new_over_old()
    {
        $importJob = new ImportJob();
        // Оба формата присутствуют - новый должен иметь приоритет
        $importJob->settings = [
            'duplicate_field' => 'UF_EMAIL',
            'duplicate_check_field' => 'UF_PHONE'
        ];

        $job = new ProcessImportJob(1);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('isDuplicate');
        $method->setAccessible(true);

        $fields = ['UF_EMAIL' => 'test@test.com', 'UF_PHONE' => '123456'];

        // Mock API service - проверяем что вызывается с UF_EMAIL (новый формат)
        $apiService = $this->createMock(Bitrix24APIService::class);
        $apiService->expects($this->once())
            ->method('call')
            ->with(
                $this->equalTo('crm.item.list'),
                $this->callback(function ($params) {
                    return isset($params['filter']['UF_EMAIL']) &&
                        $params['filter']['UF_EMAIL'] === 'test@test.com';
                })
            )
            ->willReturn(['result' => []]);

        $result = $method->invoke($job, $fields, 123, $apiService, $importJob);

        $this->assertFalse($result);
    }
}

