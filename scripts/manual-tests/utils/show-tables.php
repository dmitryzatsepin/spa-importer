#!/usr/bin/env php
<?php

require __DIR__ . '/../../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Таблицы в базе данных ===\n\n";

$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

if (empty($tables)) {
    echo "Таблиц не найдено.\n";
} else {
    echo "Найдено таблиц: " . count($tables) . "\n\n";
    foreach ($tables as $index => $table) {
        echo ($index + 1) . ". " . $table->name . "\n";
    }
}

echo "\n";

