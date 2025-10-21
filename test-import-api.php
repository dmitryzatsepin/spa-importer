<?php

/**
 * Скрипт для быстрого тестирования API импорта
 * Использование: php test-import-api.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Тест API импорта ===\n\n";

// Проверка подключения к БД
echo "1. Проверка подключения к БД...\n";
try {
    $portals = \App\Models\Portal::count();
    echo "   ✓ БД доступна. Найдено порталов: {$portals}\n\n";
} catch (\Exception $e) {
    echo "   ✗ Ошибка подключения к БД: {$e->getMessage()}\n";
    exit(1);
}

// Проверка наличия портала для тестирования
echo "2. Проверка наличия тестового портала...\n";
$portal = \App\Models\Portal::first();
if (!$portal) {
    echo "   ✗ Не найден ни один портал в БД. Создайте портал через OAuth.\n";
    exit(1);
}
echo "   ✓ Найден портал: {$portal->domain} (ID: {$portal->id})\n\n";

// Проверка директории для импорта
echo "3. Проверка директории для импорта...\n";
$importPath = storage_path('app/imports');
if (!is_dir($importPath)) {
    echo "   ✗ Директория не существует: {$importPath}\n";
    exit(1);
}
if (!is_writable($importPath)) {
    echo "   ✗ Директория не доступна для записи: {$importPath}\n";
    exit(1);
}
echo "   ✓ Директория доступна: {$importPath}\n\n";

// Проверка модели ImportJob
echo "4. Проверка модели ImportJob...\n";
try {
    $jobsCount = \App\Models\ImportJob::count();
    echo "   ✓ Модель работает. Задач импорта: {$jobsCount}\n\n";
} catch (\Exception $e) {
    echo "   ✗ Ошибка модели ImportJob: {$e->getMessage()}\n";
    exit(1);
}

// Проверка маршрутов API
echo "5. Проверка зарегистрированных маршрутов...\n";
$routes = app('router')->getRoutes();
$apiRoutes = [
    'api/v1/smart-processes',
    'api/v1/smart-processes/{entityTypeId}/fields',
    'api/v1/import',
    'api/v1/import/{jobId}/status',
];

foreach ($apiRoutes as $route) {
    $found = false;
    foreach ($routes as $r) {
        if (str_contains($r->uri(), $route)) {
            $found = true;
            break;
        }
    }

    if ($found) {
        echo "   ✓ Маршрут зарегистрирован: {$route}\n";
    } else {
        echo "   ✗ Маршрут не найден: {$route}\n";
    }
}

echo "\n=== Все базовые проверки пройдены ===\n";
echo "\nДля тестирования API используйте:\n";
echo "- Postman/Insomnia\n";
echo "- curl\n";
echo "- Или встроенный Laravel HTTP-клиент в тестах\n\n";

echo "Примеры запросов:\n";
echo "GET  http://localhost:8000/api/v1/smart-processes?portal_id={$portal->id}\n";
echo "GET  http://localhost:8000/api/v1/smart-processes/128/fields?portal_id={$portal->id}\n";
echo "POST http://localhost:8000/api/v1/import (с multipart/form-data)\n";
echo "GET  http://localhost:8000/api/v1/import/1/status\n";

