<?php

// Простой тест API для фронтенда
$baseUrl = 'http://localhost:8000';

echo "=== Тест API для фронтенда ===\n\n";

// Тест 1: Получение смарт-процессов
echo "1. Тест получения смарт-процессов:\n";
$url = $baseUrl . '/api/v1/smart-processes?portal_id=1';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && isset($data['success'])) {
    echo "   ✅ API доступен\n";
    echo "   📊 Статус: " . ($data['success'] ? 'success' : 'error') . "\n";
    if (isset($data['data'])) {
        echo "   📋 Найдено смарт-процессов: " . count($data['data']) . "\n";
    }
    if (isset($data['message'])) {
        echo "   💬 Сообщение: " . $data['message'] . "\n";
    }
} else {
    echo "   ❌ Ошибка API\n";
    echo "   📄 Ответ: " . substr($response, 0, 200) . "...\n";
}

echo "\n";

// Тест 2: Проверка главной страницы
echo "2. Тест главной страницы:\n";
$url = $baseUrl . '/?portal_id=1';
$response = file_get_contents($url);

if (strpos($response, 'id="root"') !== false) {
    echo "   ✅ React контейнер найден\n";
} else {
    echo "   ❌ React контейнер не найден\n";
}

if (strpos($response, 'app-config') !== false) {
    echo "   ✅ Конфигурация приложения найдена\n";
} else {
    echo "   ❌ Конфигурация приложения не найдена\n";
}

if (strpos($response, 'vite') !== false) {
    echo "   ✅ Vite ассеты подключены\n";
} else {
    echo "   ❌ Vite ассеты не подключены\n";
}

echo "\n";

// Тест 3: Проверка портала в базе
echo "3. Проверка портала в базе:\n";
try {
    $pdo = new PDO('sqlite:database/database.sqlite');
    $stmt = $pdo->query('SELECT id, domain, member_id FROM portals LIMIT 1');
    $portal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($portal) {
        echo "   ✅ Портал найден: ID={$portal['id']}, Domain={$portal['domain']}\n";
    } else {
        echo "   ❌ Портал не найден в базе\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка подключения к базе: " . $e->getMessage() . "\n";
}

echo "\n=== Тест завершен ===\n";
echo "\nДля тестирования фронтенда откройте:\n";
echo "http://localhost:8000/?portal_id=1\n";
echo "\nУбедитесь, что Vite dev-сервер запущен:\n";
echo "npm run dev\n";
