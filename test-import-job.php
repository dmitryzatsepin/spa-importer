<?php

/**
 * Тестовый скрипт для проверки ProcessImportJob
 * 
 * Использование:
 *   php test-import-job.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Portal;
use App\Models\ImportJob;
use App\Jobs\ProcessImportJob;
use Illuminate\Support\Facades\Storage;

echo "=== Тест ProcessImportJob ===\n\n";

// 1. Проверяем наличие портала
echo "1. Проверка портала...\n";
$portal = Portal::first();

if (!$portal) {
    echo "❌ Портал не найден. Создайте портал в БД.\n";
    echo "   Пример:\n";
    echo "   INSERT INTO portals (member_id, domain, access_token, refresh_token, expires_at, created_at, updated_at)\n";
    echo "   VALUES ('test123', 'your-portal.bitrix24.ru', 'your_token', 'your_refresh', NOW() + INTERVAL 1 HOUR, NOW(), NOW());\n";
    exit(1);
}

echo "✅ Портал найден: {$portal->domain}\n\n";

// 2. Создаем тестовый CSV файл
echo "2. Создание тестового файла...\n";

$testData = [
    ['Название', 'Дата создания', 'Ответственный', 'Сумма', 'Активен'],
    ['Тестовый элемент 1', '01.01.2024', '1', '1000.50', 'Да'],
    ['Тестовый элемент 2', '15.02.2024', '1', '2500', 'Нет'],
    ['Тестовый элемент 3', '20.03.2024', '1', '3750.75', 'Да'],
];

$csvContent = '';
foreach ($testData as $row) {
    $csvContent .= implode(';', $row) . "\n";
}

$filename = 'test_import_' . time() . '.csv';
$filepath = 'imports/' . $filename;

Storage::put($filepath, $csvContent);

if (!Storage::exists($filepath)) {
    echo "❌ Не удалось создать тестовый файл\n";
    exit(1);
}

echo "✅ Создан файл: {$filename}\n\n";

// 3. Создаем задачу импорта
echo "3. Создание задачи импорта...\n";

$importJob = ImportJob::create([
    'portal_id' => $portal->id,
    'status' => 'pending',
    'original_filename' => $filename,
    'stored_filepath' => $filepath,
    'field_mappings' => [
        [
            'source_column' => 'Название',
            'target_field' => 'TITLE',
        ],
        [
            'source_column' => 'Дата создания',
            'target_field' => 'CREATED_DATE',
            'transform' => 'date',
            'date_format' => 'd.m.Y',
        ],
        [
            'source_column' => 'Ответственный',
            'target_field' => 'ASSIGNED_BY_ID',
            'transform' => 'user',
        ],
        [
            'source_column' => 'Сумма',
            'target_field' => 'OPPORTUNITY',
            'transform' => 'number',
        ],
        [
            'source_column' => 'Активен',
            'target_field' => 'IS_ACTIVE',
            'transform' => 'boolean',
        ],
    ],
    'settings' => [
        'entity_type_id' => 128, // Замените на ID вашего смарт-процесса
        'duplicate_handling' => 'skip',
        'batch_size' => 10,
    ],
    'total_rows' => 0,
    'processed_rows' => 0,
]);

echo "✅ Задача создана ID: {$importJob->id}\n\n";

// 4. Варианты запуска
echo "4. Запуск задачи...\n";
echo "   Выберите режим запуска:\n";
echo "   a) Синхронно (для тестирования и отладки)\n";
echo "   b) Через очередь (production режим)\n\n";

$mode = readline("   Выбор (a/b): ");

if ($mode === 'b') {
    echo "\n   Постановка в очередь...\n";
    dispatch(new ProcessImportJob($importJob->id));

    echo "✅ Задача поставлена в очередь\n\n";
    echo "   Запустите воркер в другом терминале:\n";
    echo "   php artisan queue:work\n\n";
    echo "   Проверьте статус:\n";
    echo "   curl http://localhost:8000/api/import/{$importJob->id}/status\n\n";

} else {
    echo "\n   Синхронный запуск...\n";
    echo "   (это может занять некоторое время)\n\n";

    try {
        $job = new ProcessImportJob($importJob->id);
        $job->handle();

        echo "✅ Задача выполнена\n\n";

        // Обновляем данные
        $importJob->refresh();

        echo "5. Результаты:\n";
        echo "   Статус: {$importJob->status}\n";
        echo "   Всего строк: {$importJob->total_rows}\n";
        echo "   Обработано: {$importJob->processed_rows}\n";
        echo "   Прогресс: {$importJob->getProgressPercentage()}%\n";

        if ($importJob->error_details) {
            echo "   Ошибки: " . json_encode($importJob->error_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        echo "\n";

        if ($importJob->status === 'completed') {
            echo "🎉 Импорт успешно завершен!\n";
            echo "   Проверьте смарт-процесс в Битрикс24\n";
        } else {
            echo "⚠️  Импорт завершен со статусом: {$importJob->status}\n";
        }

    } catch (\Exception $e) {
        echo "❌ Ошибка выполнения:\n";
        echo "   " . $e->getMessage() . "\n";
        echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n\n";

        // Обновляем данные
        $importJob->refresh();

        if ($importJob->error_details) {
            echo "   Детали ошибки:\n";
            echo "   " . json_encode($importJob->error_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

echo "\n=== Тест завершен ===\n";

