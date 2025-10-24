<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Portal;

// Загружаем Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Проверка базы данных ===\n\n";

try {
    $portals = Portal::all();
    
    if ($portals->isEmpty()) {
        echo "❌ В базе данных нет записей порталов\n";
    } else {
        echo "✅ Найдено порталов: " . $portals->count() . "\n\n";
        
        foreach ($portals as $portal) {
            echo "--- Портала #{$portal->id} ---\n";
            echo "Member ID: " . ($portal->member_id ?: 'Отсутствует') . "\n";
            echo "Domain: " . ($portal->domain ?: 'Отсутствует') . "\n";
            echo "Access Token: " . ($portal->access_token ? 'Присутствует (' . strlen($portal->access_token) . ' символов)' : 'Отсутствует') . "\n";
            echo "Refresh Token: " . ($portal->refresh_token ? 'Присутствует (' . strlen($portal->refresh_token) . ' символов)' : 'Отсутствует') . "\n";
            echo "Client ID: " . ($portal->client_id ?: 'Отсутствует') . "\n";
            echo "Client Secret: " . ($portal->client_secret ? 'Присутствует (' . strlen($portal->client_secret) . ' символов)' : 'Отсутствует') . "\n";
            echo "Expires At: " . ($portal->expires_at ? $portal->expires_at->format('Y-m-d H:i:s') : 'Отсутствует') . "\n";
            echo "Created At: " . ($portal->created_at ? $portal->created_at->format('Y-m-d H:i:s') : 'Отсутствует') . "\n";
            echo "Updated At: " . ($portal->updated_at ? $portal->updated_at->format('Y-m-d H:i:s') : 'Отсутствует') . "\n";
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка при проверке базы данных: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "=== Конец проверки ===\n";
