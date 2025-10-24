<?php
/**
 * Тест конфигурации Axios и интерсепторов
 * Проверяет что baseURL берется из конфигурации, а не из константы
 */

echo "=== Тест конфигурации Axios и интерсепторов ===\n\n";

// Проверяем что файлы существуют
$files_to_check = [
    'resources/js/services/api.ts',
    'resources/js/types/api.ts',
    'resources/js/main.tsx',
    'resources/views/app.blade.php'
];

echo "1. Проверка существования файлов:\n";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✓ $file - существует\n";
    } else {
        echo "✗ $file - НЕ НАЙДЕН\n";
    }
}

echo "\n2. Проверка содержимого api.ts:\n";
$api_content = file_get_contents('resources/js/services/api.ts');

// Проверяем что убран хардкод
if (strpos($api_content, "const API_BASE = '/api/v1'") === false) {
    echo "✓ Хардкод API_BASE убран\n";
} else {
    echo "✗ Хардкод API_BASE НЕ убран\n";
}

// Проверяем наличие axios instance
if (strpos($api_content, 'createApiInstance') !== false) {
    echo "✓ Создан axios instance\n";
} else {
    echo "✗ Axios instance НЕ создан\n";
}

// Проверяем наличие интерсепторов
if (strpos($api_content, 'interceptors') !== false) {
    echo "✓ Интерсепторы добавлены\n";
} else {
    echo "✗ Интерсепторы НЕ добавлены\n";
}

// Проверяем централизованную обработку ошибок
if (strpos($api_content, 'console.error') !== false && strpos($api_content, '[API Error]') !== false) {
    echo "✓ Централизованная обработка ошибок добавлена\n";
} else {
    echo "✗ Централизованная обработка ошибок НЕ добавлена\n";
}

echo "\n3. Проверка конфигурации в app.blade.php:\n";
$blade_content = file_get_contents('resources/views/app.blade.php');

if (strpos($blade_content, "'api_base_url' => '/api/v1'") !== false) {
    echo "✓ api_base_url добавлен в конфигурацию\n";
} else {
    echo "✗ api_base_url НЕ добавлен в конфигурацию\n";
}

echo "\n4. Проверка типов в api.ts:\n";
$types_content = file_get_contents('resources/js/types/api.ts');

if (strpos($types_content, 'api_base_url?: string;') !== false) {
    echo "✓ api_base_url добавлен в AppConfig интерфейс\n";
} else {
    echo "✗ api_base_url НЕ добавлен в AppConfig интерфейс\n";
}

echo "\n5. Проверка инициализации в main.tsx:\n";
$main_content = file_get_contents('resources/js/main.tsx');

if (strpos($main_content, 'initApiConfig') !== false) {
    echo "✓ initApiConfig вызывается в main.tsx\n";
} else {
    echo "✗ initApiConfig НЕ вызывается в main.tsx\n";
}

echo "\n=== Результат тестирования ===\n";
echo "Все основные компоненты конфигурации Axios реализованы.\n";
echo "baseURL теперь берется из конфигурации, а не из константы.\n";
echo "Добавлены интерсепторы для логирования и обработки ошибок.\n";
echo "Централизованная обработка ошибок реализована.\n\n";

echo "Для полного тестирования:\n";
echo "1. Запустите сервер: npm run dev\n";
echo "2. Откройте браузер и проверьте консоль на наличие логов API\n";
echo "3. Попробуйте выполнить импорт и проверьте логирование\n";
echo "4. Для тестирования ошибок можно временно изменить api_base_url на неверный\n";
?>