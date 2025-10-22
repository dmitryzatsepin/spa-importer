<?php

/**
 * Скрипт для тестирования механизма автоматического обновления токенов
 * 
 * Использование:
 * php test-token-refresh.php
 */

require __DIR__ . '/../../../vendor/autoload.php';

use App\Models\Portal;
use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Exceptions\TokenRefreshException;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use Illuminate\Support\Facades\Log;

// Загрузка Laravel
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тест механизма автоматического обновления токенов ===\n\n";

// Получаем первый портал из БД
$portal = Portal::first();

if (!$portal) {
    echo "❌ Ошибка: В БД нет ни одного портала.\n";
    echo "   Пожалуйста, сначала установите приложение через /install\n";
    exit(1);
}

echo "✓ Найден портал:\n";
echo "  - ID: {$portal->id}\n";
echo "  - Domain: {$portal->domain}\n";
echo "  - Expires At: {$portal->expires_at->toDateTimeString()}\n";
echo "  - Is Expired: " . ($portal->isTokenExpired() ? 'ДА' : 'НЕТ') . "\n";
echo "  - Needs Refresh: " . ($portal->needsTokenRefresh() ? 'ДА' : 'НЕТ') . "\n\n";

// Опция для принудительного истечения токена
if (isset($argv[1]) && $argv[1] === '--expire') {
    echo "⚠ Принудительно истекаем токен...\n";
    $portal->expires_at = now()->subMinutes(2);
    $portal->save();
    echo "✓ Токен истек\n\n";
}

// Создаем сервис с порталом
echo "Создаем Bitrix24APIService с моделью Portal...\n";
$service = new Bitrix24APIService(
    $portal->domain,
    $portal->access_token,
    30,
    5,
    $portal
);

echo "✓ Сервис создан\n\n";

// Запоминаем токен ДО запроса
$tokenBefore = $portal->access_token;
$expiresBefore = $portal->expires_at->toDateTimeString();

echo "Выполняем API-запрос (app.info)...\n";
echo "Токен ДО запроса: " . substr($tokenBefore, 0, 20) . "...\n";
echo "Expires ДО запроса: {$expiresBefore}\n\n";

try {
    $result = $service->call('app.info');

    // Перезагружаем портал из БД
    $portal->refresh();

    $tokenAfter = $portal->access_token;
    $expiresAfter = $portal->expires_at->toDateTimeString();

    echo "✓ Запрос выполнен успешно!\n\n";

    echo "Токен ПОСЛЕ запроса: " . substr($tokenAfter, 0, 20) . "...\n";
    echo "Expires ПОСЛЕ запроса: {$expiresAfter}\n\n";

    if ($tokenBefore !== $tokenAfter) {
        echo "🔄 ТОКЕН БЫЛ АВТОМАТИЧЕСКИ ОБНОВЛЕН!\n";
        echo "   Старый expires: {$expiresBefore}\n";
        echo "   Новый expires: {$expiresAfter}\n";
    } else {
        echo "✓ Токен остался прежним (не требовал обновления)\n";
    }

    echo "\nРезультат API-запроса:\n";
    echo "  - App: {$result['result']['ID']} ({$result['result']['LICENSE']})\n";

} catch (TokenRefreshException $e) {
    echo "❌ Ошибка обновления токена:\n";
    echo "   {$e->getMessage()}\n";
    echo "   Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);

} catch (Bitrix24APIException $e) {
    echo "❌ Ошибка API Битрикс24:\n";
    echo "   {$e->getMessage()}\n";
    echo "   Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

echo "\n=== Тест завершен успешно! ===\n";

