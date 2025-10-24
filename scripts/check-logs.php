<?php

echo "=== Проверка логов ===\n\n";

// Проверяем логи Laravel
$logPath = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logPath)) {
    echo "📋 Laravel лог (последние 20 строк):\n";
    $lines = file($logPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo "\n";
} else {
    echo "❌ Laravel лог не найден: $logPath\n\n";
}

// Проверяем логи Angie
$angieLogPath = '/var/log/angie/spa-importer-error.log';
if (file_exists($angieLogPath)) {
    echo "📋 Angie error лог (последние 20 строк):\n";
    $lines = file($angieLogPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo "\n";
} else {
    echo "❌ Angie error лог не найден: $angieLogPath\n\n";
}

$angieAccessLogPath = '/var/log/angie/spa-importer-access.log';
if (file_exists($angieAccessLogPath)) {
    echo "📋 Angie access лог (последние 20 строк):\n";
    $lines = file($angieAccessLogPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo "\n";
} else {
    echo "❌ Angie access лог не найден: $angieAccessLogPath\n\n";
}

echo "=== Конец проверки логов ===\n";
