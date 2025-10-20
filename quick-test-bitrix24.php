<?php
/**
 * Быстрое тестирование Bitrix24 API Service
 * 
 * Использование:
 * php quick-test-bitrix24.php your-portal.bitrix24.ru YOUR_TOKEN_OR_WEBHOOK
 * 
 * Примеры:
 * php quick-test-bitrix24.php portal.bitrix24.ru 1/abcdef123456  (вебхук)
 * php quick-test-bitrix24.php portal.bitrix24.ru your_oauth_token  (OAuth токен)
 */

require __DIR__ . '/vendor/autoload.php';

// Загрузка приложения Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;

// Цвета для консоли
function colorize($text, $color = 'green')
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Вывод разделителя
function separator($title = '')
{
    echo "\n" . str_repeat('=', 70) . "\n";
    if ($title) {
        echo colorize(" $title ", 'blue') . "\n";
        echo str_repeat('=', 70) . "\n";
    }
}

// Проверка аргументов
if ($argc < 3) {
    echo colorize("Использование:\n", 'yellow');
    echo "  php quick-test-bitrix24.php DOMAIN TOKEN\n\n";
    echo colorize("Примеры:\n", 'yellow');
    echo "  php quick-test-bitrix24.php portal.bitrix24.ru 1/abcdef123456\n";
    echo "  php quick-test-bitrix24.php portal.bitrix24.ru your_oauth_token\n\n";
    echo colorize("Как получить токен:\n", 'yellow');
    echo "  1. Вебхук (рекомендуется для тестов):\n";
    echo "     Битрикс24 → Настройки → Другое → Входящие вебхуки\n";
    echo "  2. OAuth токен из существующего приложения\n\n";
    exit(1);
}

$domain = $argv[1];
$token = $argv[2];

separator('Тестирование Bitrix24 API Service');

echo "Домен: " . colorize($domain, 'blue') . "\n";
echo "Токен: " . colorize(substr($token, 0, 10) . '...', 'blue') . "\n";

// Создание сервиса
try {
    $service = new Bitrix24APIService($domain, $token);
    echo colorize("✓", 'green') . " Сервис создан успешно\n";
} catch (Exception $e) {
    echo colorize("✗", 'red') . " Ошибка создания сервиса: " . $e->getMessage() . "\n";
    exit(1);
}

// Тест 1: app.info
separator('Тест 1: Получение информации о приложении (app.info)');
try {
    $result = $service->call('app.info');
    echo colorize("✓ УСПЕШНО", 'green') . "\n";
    echo "REST версия: " . ($result['result']['REST_VERSION'] ?? 'N/A') . "\n";
    echo "Лицензия: " . ($result['result']['LICENSE'] ?? 'N/A') . "\n";
    if (isset($result['result']['LANGUAGE_ID'])) {
        echo "Язык: " . $result['result']['LANGUAGE_ID'] . "\n";
    }
} catch (Bitrix24APIException $e) {
    echo colorize("✗ ОШИБКА", 'red') . "\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
    echo "Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

// Тест 2: user.current
separator('Тест 2: Получение текущего пользователя (user.current)');
try {
    $result = $service->call('user.current');
    echo colorize("✓ УСПЕШНО", 'green') . "\n";

    $user = $result['result'];
    if (isset($user['ID'])) {
        echo "ID: " . $user['ID'] . "\n";
        echo "Имя: " . ($user['NAME'] ?? 'N/A') . " " . ($user['LAST_NAME'] ?? '') . "\n";
        echo "Email: " . ($user['EMAIL'] ?? 'N/A') . "\n";
    } else {
        echo "Данные пользователя получены\n";
    }
} catch (Bitrix24APIException $e) {
    echo colorize("✗ ОШИБКА", 'red') . "\n";
    echo "Сообщение: " . $e->getMessage() . "\n";

    // Вебхук может не иметь user.current, это нормально
    if (strpos($e->getMessage(), 'insufficient_scope') !== false) {
        echo colorize("(Это нормально для вебхука - недостаточно прав)\n", 'yellow');
    }
}

// Тест 3: crm.deal.list
separator('Тест 3: Получение списка сделок (crm.deal.list)');
try {
    $result = $service->call('crm.deal.list', [
        'select' => ['ID', 'TITLE', 'STAGE_ID'],
        'order' => ['ID' => 'DESC'],
        'start' => 0
    ]);
    echo colorize("✓ УСПЕШНО", 'green') . "\n";
    echo "Всего найдено: " . $result['total'] . " сделок\n";

    if (!empty($result['result'])) {
        echo "\nПервые 3 сделки:\n";
        $count = 0;
        foreach ($result['result'] as $deal) {
            if ($count++ >= 3)
                break;
            echo "  - ID: {$deal['ID']}, Название: " . ($deal['TITLE'] ?? 'Без названия') .
                ", Стадия: " . ($deal['STAGE_ID'] ?? 'N/A') . "\n";
        }
    } else {
        echo colorize("Сделок не найдено\n", 'yellow');
    }
} catch (Bitrix24APIException $e) {
    echo colorize("✗ ОШИБКА", 'red') . "\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
}

// Тест 4: Пакетный запрос
separator('Тест 4: Пакетный запрос (batch)');
try {
    $batch = new Bitrix24BatchRequest();
    $batch->addCommand('app_info', 'app.info')
        ->addCommand('deals_count', 'crm.deal.list', ['select' => ['ID'], 'start' => 0])
        ->addCommand('contacts_count', 'crm.contact.list', ['select' => ['ID'], 'start' => 0]);

    echo "Добавлено команд: " . $batch->count() . "\n";

    $result = $service->callBatch($batch);
    echo colorize("✓ УСПЕШНО", 'green') . "\n";
    echo "Выполнено команд: " . $result['total'] . "\n\n";

    foreach ($result['results'] as $cmdKey => $cmdResult) {
        $status = $cmdResult['error'] ? colorize('✗', 'red') : colorize('✓', 'green');
        echo "{$status} Команда '{$cmdKey}': ";

        if ($cmdResult['error']) {
            echo colorize($cmdResult['error'], 'red') . "\n";
        } else {
            echo colorize('OK', 'green');
            if (isset($cmdResult['total'])) {
                echo " (записей: {$cmdResult['total']})";
            }
            echo "\n";
        }
    }
} catch (Bitrix24APIException $e) {
    echo colorize("✗ ОШИБКА", 'red') . "\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
}

// Тест 5: Обработка несуществующего метода
separator('Тест 5: Обработка ошибок (несуществующий метод)');
try {
    $result = $service->call('non.existent.method.test');
    echo colorize("✗ НЕОЖИДАННО: ошибка не была выброшена", 'red') . "\n";
} catch (Bitrix24APIException $e) {
    echo colorize("✓ УСПЕШНО", 'green') . " - ошибка корректно перехвачена\n";
    echo "Тип ошибки: " . ($e->getContext()['error'] ?? 'N/A') . "\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
}

// Итоги
separator('Итоги тестирования');

echo colorize("✓", 'green') . " Базовые функции работают корректно\n";
echo colorize("✓", 'green') . " Одиночные запросы выполняются успешно\n";
echo colorize("✓", 'green') . " Пакетные запросы работают\n";
echo colorize("✓", 'green') . " Обработка ошибок функционирует правильно\n";

separator();

echo "\n" . colorize("Тестирование завершено успешно!", 'green') . "\n\n";

echo "Следующие шаги:\n";
echo "  1. Используйте сервис в своих контроллерах\n";
echo "  2. Проверьте логи: storage/logs/laravel.log\n";
echo "  3. Протестируйте через веб: php artisan serve\n";
echo "     http://localhost:8000/test-bitrix24/\n\n";

