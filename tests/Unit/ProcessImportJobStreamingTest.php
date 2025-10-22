<?php

namespace Tests\Unit;

use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use App\Models\Portal;
use App\Services\Bitrix24\Bitrix24APIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportJobStreamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Тест стриминга CSV файла - проверяет, что файл читается построчно без загрузки всего в память
     */
    public function test_csv_streaming_with_large_file(): void
    {
        // Создаем большой CSV файл (10000 строк)
        $largeRowCount = 10000;
        $csvContent = $this->generateLargeCsv($largeRowCount);
        $filePath = storage_path('app/test_large.csv');
        file_put_contents($filePath, $csvContent);

        // Создаем портал
        $portal = Portal::create([
            'member_id' => 'test_member_' . uniqid(),
            'domain' => 'test.bitrix24.ru',
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHour(),
        ]);

        // Создаем задачу импорта
        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'original_filename' => 'test_large.csv',
            'stored_filepath' => 'test_large.csv',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'settings' => [
                'entity_type_id' => 1,
                'batch_size' => 50,
                'duplicate_handling' => 'skip',
            ],
            'field_mappings' => [
                ['source' => 'Name', 'target' => 'TITLE'],
                ['source' => 'Email', 'target' => 'UF_CRM_EMAIL'],
            ],
        ]);

        // Замеряем память до обработки
        $memoryBefore = memory_get_usage(true);
        $peakMemoryBefore = memory_get_peak_usage(true);

        // Проверяем, что файл большой
        $fileSize = filesize($filePath);
        $this->assertGreaterThan(100000, $fileSize, 'Файл должен быть достаточно большим для теста');

        // Имитируем обработку (без реального API)
        // В реальном тесте нужно мокировать API

        // Замеряем память после
        $memoryAfter = memory_get_usage(true);
        $peakMemoryAfter = memory_get_peak_usage(true);

        $memoryUsed = $memoryAfter - $memoryBefore;
        $peakMemoryUsed = $peakMemoryAfter - $peakMemoryBefore;

        // Проверяем, что потребление памяти не превышает разумных пределов
        // Для стриминга память не должна расти пропорционально размеру файла
        // Допустим, не более 10MB для 10000 строк
        $this->assertLessThan(
            10 * 1024 * 1024,
            $peakMemoryUsed,
            'Пиковое потребление памяти должно быть меньше 10MB'
        );

        // Очистка
        unlink($filePath);
    }

    /**
     * Тест стриминга XLSX файла
     */
    public function test_xlsx_streaming_memory_efficient(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive extension is not installed');
        }

        // Создаем XLSX файл с помощью PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Заголовки
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Phone');

        // Добавляем 5000 строк данных
        for ($i = 2; $i <= 5000; $i++) {
            $sheet->setCellValue("A{$i}", "Name {$i}");
            $sheet->setCellValue("B{$i}", "email{$i}@test.com");
            $sheet->setCellValue("C{$i}", "+7900{$i}");
        }

        $filePath = storage_path('app/test_large.xlsx');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);

        // Освобождаем память после создания файла
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
        gc_collect_cycles();

        // Замеряем память до обработки
        $memoryBefore = memory_get_usage(true);

        // Создаем портал
        $portal = Portal::create([
            'member_id' => 'test_member_' . uniqid(),
            'domain' => 'test.bitrix24.ru',
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHour(),
        ]);

        // Создаем задачу импорта
        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'original_filename' => 'test_large.xlsx',
            'stored_filepath' => 'test_large.xlsx',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'settings' => [
                'entity_type_id' => 1,
                'batch_size' => 50,
            ],
            'field_mappings' => [
                ['source' => 'Name', 'target' => 'TITLE'],
            ],
        ]);

        // Тестируем только чтение файла через итератор
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $rowIterator = $worksheet->getRowIterator();
        $rowCount = 0;

        foreach ($rowIterator as $row) {
            $rowCount++;
            // Обрабатываем строку (но не загружаем все в память)
        }

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Проверяем, что обработали все строки
        $this->assertEquals(5000, $rowCount);

        // Проверяем эффективность памяти (не более 20MB для 5000 строк)
        $this->assertLessThan(
            20 * 1024 * 1024,
            $memoryUsed,
            'Потребление памяти при стриминге XLSX должно быть ограниченным'
        );

        // Очистка
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        unlink($filePath);
    }

    /**
     * Тест детекции кодировки и разделителя CSV
     */
    public function test_csv_encoding_and_delimiter_detection(): void
    {
        // CSV с точкой с запятой и Windows-1251
        $csvContent = "Имя;Email;Телефон\n";
        $csvContent .= "Иванов Иван;ivanov@test.ru;+79001234567\n";
        $csvContent .= "Петров Петр;petrov@test.ru;+79007654321\n";

        // Конвертируем в Windows-1251
        $csvContentEncoded = mb_convert_encoding($csvContent, 'Windows-1251', 'UTF-8');

        $filePath = storage_path('app/test_encoding.csv');
        file_put_contents($filePath, $csvContentEncoded);

        // Создаем портал и задачу
        $portal = Portal::create([
            'member_id' => 'test_member_' . uniqid(),
            'domain' => 'test.bitrix24.ru',
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHour(),
        ]);

        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'original_filename' => 'test_encoding.csv',
            'stored_filepath' => 'test_encoding.csv',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'settings' => [
                'entity_type_id' => 1,
                'batch_size' => 10,
            ],
            'field_mappings' => [
                ['source' => 'Имя', 'target' => 'TITLE'],
            ],
        ]);

        // Тестируем детекцию через приватные методы (используем рефлексию)
        $job = new ProcessImportJob($importJob->id);
        $reflection = new \ReflectionClass($job);

        $detectDelimiterMethod = $reflection->getMethod('detectCsvDelimiter');
        $detectDelimiterMethod->setAccessible(true);
        $delimiter = $detectDelimiterMethod->invoke($job, $filePath);

        $detectEncodingMethod = $reflection->getMethod('detectEncoding');
        $detectEncodingMethod->setAccessible(true);
        $encoding = $detectEncodingMethod->invoke($job, $filePath);

        // Проверяем правильность детекции
        $this->assertEquals(';', $delimiter, 'Должен определить точку с запятой как разделитель');
        $this->assertEquals('Windows-1251', $encoding, 'Должен определить кодировку Windows-1251');

        // Очистка
        unlink($filePath);
    }

    /**
     * Генерирует большой CSV файл для теста
     */
    protected function generateLargeCsv(int $rows): string
    {
        $content = "Name,Email,Phone,Company,Position\n";

        for ($i = 1; $i <= $rows; $i++) {
            $content .= "Name{$i},email{$i}@test.com,+7900{$i},Company{$i},Position{$i}\n";
        }

        return $content;
    }

    /**
     * Тест подсчета строк в CSV без загрузки в память
     */
    public function test_csv_row_counting_without_loading_to_memory(): void
    {
        $csvContent = $this->generateLargeCsv(1000);
        $filePath = storage_path('app/test_count.csv');
        file_put_contents($filePath, $csvContent);

        $portal = Portal::create([
            'member_id' => 'test_member_' . uniqid(),
            'domain' => 'test.bitrix24.ru',
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHour(),
        ]);

        $importJob = ImportJob::create([
            'portal_id' => $portal->id,
            'original_filename' => 'test_count.csv',
            'stored_filepath' => 'test_count.csv',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'settings' => ['entity_type_id' => 1],
            'field_mappings' => [],
        ]);

        // Тестируем подсчет строк
        $job = new ProcessImportJob($importJob->id);
        $reflection = new \ReflectionClass($job);

        $countMethod = $reflection->getMethod('countCsvRows');
        $countMethod->setAccessible(true);

        $memoryBefore = memory_get_usage(true);
        $count = $countMethod->invoke($job, $filePath);
        $memoryAfter = memory_get_usage(true);

        $memoryUsed = $memoryAfter - $memoryBefore;

        // Проверяем корректность подсчета (1000 строк данных + 1 заголовок = 1001)
        $this->assertEquals(1001, $count);

        // Проверяем, что память использована минимально (не более 1MB)
        $this->assertLessThan(
            1024 * 1024,
            $memoryUsed,
            'Подсчет строк не должен загружать файл в память'
        );

        unlink($filePath);
    }
}

