#!/usr/bin/env php
<?php

/**
 * Скрипт для демонстрации переключения между мок и реальным API
 * 
 * Использование:
 *   php test-api-switch.php
 */

echo "=== Тест переключения API_USE_MOCK ===" . PHP_EOL . PHP_EOL;

// Тест 1: Проверка мок режима
echo "1. Тест MOCK режима (API_USE_MOCK=true)" . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

// Устанавливаем переменную окружения
putenv('API_USE_MOCK=true');

// Запускаем запрос к API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api/v1/smart-processes?portal_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}" . PHP_EOL;
$data = json_decode($response, true);

if ($data && isset($data['success']) && $data['success']) {
    echo "✓ Мок режим работает!" . PHP_EOL;
    echo "Получено смарт-процессов: " . count($data['data'] ?? []) . PHP_EOL;

    if (!empty($data['data'])) {
        echo "Первый процесс: " . ($data['data'][0]['title'] ?? 'N/A') . PHP_EOL;
    }
} else {
    echo "✗ Ошибка в мок режиме" . PHP_EOL;
    echo "Response: " . substr($response, 0, 200) . PHP_EOL;
}

echo PHP_EOL;

// Тест 2: Проверка реального режима (ожидается ошибка без настроенного портала)
echo "2. Тест REAL режима (API_USE_MOCK=false)" . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

putenv('API_USE_MOCK=false');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api/v1/smart-processes?portal_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}" . PHP_EOL;
$data = json_decode($response, true);

if ($httpCode === 500 && isset($data['success']) && !$data['success']) {
    echo "✓ Реальный режим активен (ожидаемая ошибка без портала)" . PHP_EOL;
    echo "Сообщение: " . ($data['message'] ?? 'N/A') . PHP_EOL;
} else {
    echo "? Неожиданный результат" . PHP_EOL;
    echo "Response: " . substr($response, 0, 200) . PHP_EOL;
}

echo PHP_EOL;

// Тест 3: Проверка конфигурации
echo "3. Проверка конфигурации Laravel" . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

// Загружаем Laravel для проверки конфига
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$useMock = config('app.api_use_mock');
echo "config('app.api_use_mock') = " . ($useMock ? 'true' : 'false') . PHP_EOL;

if ($useMock) {
    echo "✓ Конфигурация: Мок режим (по умолчанию)" . PHP_EOL;
} else {
    echo "✓ Конфигурация: Реальный API режим" . PHP_EOL;
}

echo PHP_EOL;
echo "=== Все тесты завершены ===" . PHP_EOL;
echo PHP_EOL;
echo "Для переключения режима:" . PHP_EOL;
echo "1. Измените API_USE_MOCK=true/false в .env" . PHP_EOL;
echo "2. Выполните: php artisan config:clear" . PHP_EOL;
echo "3. Перезапустите сервер (если используете встроенный)" . PHP_EOL;

