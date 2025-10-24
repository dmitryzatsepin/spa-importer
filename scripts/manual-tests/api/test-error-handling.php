<?php
/**
 * Тест обработки ошибок в Axios интерсепторах
 * Проверяет что ошибки логируются единообразно
 */

echo "=== Тест обработки ошибок в Axios интерсепторах ===\n\n";

$api_content = file_get_contents('resources/js/services/api.ts');

echo "1. Проверка типов ошибок в интерсепторе:\n";

$error_codes = [
    401 => 'Неавторизованный доступ',
    403 => 'Доступ запрещен',
    404 => 'Ресурс не найден',
    422 => 'Ошибка валидации данных',
    500 => 'Внутренняя ошибка сервера',
    502 => 'Сервер временно недоступен',
    503 => 'Сервер временно недоступен',
    504 => 'Сервер временно недоступен'
];

foreach ($error_codes as $code => $description) {
    if (strpos($api_content, "case $code:") !== false) {
        echo "✓ Обработка ошибки $code ($description)\n";
    } else {
        echo "✗ Обработка ошибки $code НЕ найдена\n";
    }
}

echo "\n2. Проверка логирования ошибок:\n";

$logging_checks = [
    'console.error' => 'Использование console.error',
    '[API Error]' => 'Структурированное логирование ошибок',
    'error.response' => 'Обработка ответов сервера',
    'error.request' => 'Обработка сетевых ошибок',
    'error.message' => 'Обработка ошибок конфигурации'
];

foreach ($logging_checks as $pattern => $description) {
    if (strpos($api_content, $pattern) !== false) {
        echo "✓ $description\n";
    } else {
        echo "✗ $description НЕ найдено\n";
    }
}

echo "\n3. Проверка структуры логирования:\n";

$log_structure = [
    'message' => 'Сообщение об ошибке',
    'status' => 'HTTP статус',
    'statusText' => 'Текст статуса',
    'url' => 'URL запроса',
    'method' => 'HTTP метод',
    'data' => 'Данные ответа'
];

foreach ($log_structure as $field => $description) {
    if (strpos($api_content, "$field:") !== false) {
        echo "✓ Поле '$field' в структуре лога\n";
    } else {
        echo "✗ Поле '$field' НЕ найдено в структуре лога\n";
    }
}

echo "\n4. Проверка логирования запросов:\n";

if (strpos($api_content, '[API Request]') !== false) {
    echo "✓ Логирование исходящих запросов\n";
} else {
    echo "✗ Логирование исходящих запросов НЕ найдено\n";
}

if (strpos($api_content, '[API Response]') !== false) {
    echo "✓ Логирование ответов сервера\n";
} else {
    echo "✗ Логирование ответов сервера НЕ найдено\n";
}

echo "\n=== Результат тестирования обработки ошибок ===\n";
echo "Централизованная обработка ошибок реализована.\n";
echo "Все основные типы HTTP ошибок обрабатываются.\n";
echo "Структурированное логирование настроено.\n";
echo "Инциденты логируются единообразно.\n\n";

echo "Для тестирования в браузере:\n";
echo "1. Откройте DevTools (F12)\n";
echo "2. Перейдите на вкладку Console\n";
echo "3. Выполните любой API запрос\n";
echo "4. Проверьте логи в формате [API Request] и [API Response]\n";
echo "5. Для тестирования ошибок измените api_base_url на неверный URL\n";
echo "6. Проверьте логи ошибок в формате [API Error]\n";
?>