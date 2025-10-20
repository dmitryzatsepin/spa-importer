# Bitrix24 API Service

Сервис для работы с REST API Битрикс24 в Laravel приложении.

## Установка

Все необходимые файлы уже созданы в директории `app/Services/Bitrix24/`.

## Использование

### Создание экземпляра сервиса

```php
use App\Services\Bitrix24\Bitrix24APIService;

$service = new Bitrix24APIService(
    domain: 'your-portal.bitrix24.ru',
    accessToken: 'your_access_token',
    timeout: 30,           // опционально, по умолчанию 30 секунд
    connectTimeout: 5      // опционально, по умолчанию 5 секунд
);
```

### Одиночный запрос

```php
try {
    $result = $service->call('app.info');
    
    echo "Результат: " . print_r($result['result'], true);
    echo "Всего записей: " . $result['total'];
    echo "Время выполнения: " . print_r($result['time'], true);
    
} catch (\App\Services\Bitrix24\Exceptions\Bitrix24APIException $e) {
    echo "Ошибка: " . $e->getMessage();
    echo "Контекст: " . print_r($e->getContext(), true);
}
```

### Запрос с параметрами

```php
$result = $service->call('crm.deal.list', [
    'filter' => ['STAGE_ID' => 'WON'],
    'select' => ['ID', 'TITLE', 'STAGE_ID'],
    'order' => ['DATE_CREATE' => 'DESC'],
    'start' => 0
]);

foreach ($result['result'] as $deal) {
    echo "Сделка #{$deal['ID']}: {$deal['TITLE']}\n";
}
```

### Пакетный запрос

```php
use App\Services\Bitrix24\Bitrix24BatchRequest;

$batchRequest = new Bitrix24BatchRequest();

// Добавляем команды в пакет
$batchRequest
    ->addCommand('app_info', 'app.info')
    ->addCommand('current_user', 'user.current')
    ->addCommand('deals', 'crm.deal.list', [
        'filter' => ['STAGE_ID' => 'NEW'],
        'select' => ['ID', 'TITLE']
    ])
    ->setHalt(false); // Продолжать выполнение при ошибке

try {
    $result = $service->callBatch($batchRequest);
    
    echo "Выполнено команд: " . $result['total'] . "\n";
    
    // Обработка результатов каждой команды
    foreach ($result['results'] as $commandKey => $commandResult) {
        echo "\nКоманда: {$commandKey}\n";
        
        if ($commandResult['error']) {
            echo "Ошибка: " . $commandResult['error'] . "\n";
        } else {
            echo "Результат: " . print_r($commandResult['result'], true) . "\n";
            echo "Всего записей: " . $commandResult['total'] . "\n";
        }
    }
    
} catch (\App\Services\Bitrix24\Exceptions\Bitrix24APIException $e) {
    echo "Ошибка пакетного запроса: " . $e->getMessage();
}
```

### Обработка ошибок

```php
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;

try {
    $result = $service->call('some.method', $params);
    
} catch (Bitrix24APIException $e) {
    // Получить сообщение об ошибке
    $errorMessage = $e->getMessage();
    
    // Получить дополнительный контекст ошибки
    $context = $e->getContext();
    
    // В контексте может быть:
    // - method: метод API
    // - error: код ошибки от Битрикс24
    // - error_description: описание ошибки
    // - params: параметры запроса
    // - status: HTTP статус
    
    // Логирование
    \Log::error('Bitrix24 API Error', [
        'message' => $errorMessage,
        'context' => $context
    ]);
    
    // Обработка специфичных ошибок
    if (isset($context['error'])) {
        switch ($context['error']) {
            case 'expired_token':
                // Обновить токен
                break;
            case 'insufficient_scope':
                // Запросить дополнительные права
                break;
        }
    }
}
```

## Методы Bitrix24BatchRequest

### addCommand(string $key, string $method, array $params = [])

Добавить команду в пакетный запрос.

### setHalt(bool $halt)

Установить флаг останова при ошибке (по умолчанию false).

### getCommands(): array

Получить все команды.

### hasCommands(): bool

Проверить наличие команд.

### count(): int

Получить количество команд.

### clear()

Очистить все команды.

## Ограничения

- Максимальный размер пакетного запроса: 50 команд
- Timeout по умолчанию: 30 секунд
- Connect timeout по умолчанию: 5 секунд

## Тестирование

Для тестирования сервиса доступны специальные маршруты:

```http
GET /test-bitrix24/
GET /test-bitrix24/single?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/batch?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/error?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/invalid-token?domain=your-portal.bitrix24.ru
```

## Примеры использования в контроллерах

```php
namespace App\Http\Controllers;

use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Models\Portal;

class DealController extends Controller
{
    public function index(Portal $portal)
    {
        $service = new Bitrix24APIService(
            $portal->domain,
            $portal->access_token
        );
        
        $result = $service->call('crm.deal.list', [
            'filter' => ['STAGE_ID' => 'NEW'],
            'select' => ['ID', 'TITLE', 'OPPORTUNITY'],
            'order' => ['DATE_CREATE' => 'DESC']
        ]);
        
        return view('deals.index', [
            'deals' => $result['result'],
            'total' => $result['total']
        ]);
    }
    
    public function batchUpdate(Portal $portal, array $dealIds)
    {
        $service = new Bitrix24APIService(
            $portal->domain,
            $portal->access_token
        );
        
        $batch = new Bitrix24BatchRequest();
        
        foreach ($dealIds as $dealId) {
            $batch->addCommand(
                "deal_{$dealId}",
                'crm.deal.update',
                [
                    'id' => $dealId,
                    'fields' => ['STAGE_ID' => 'WON']
                ]
            );
        }
        
        $result = $service->callBatch($batch);
        
        return response()->json($result);
    }
}
```

## Логирование

Все ошибки автоматически логируются с использованием Laravel Log facade. Логи сохраняются в стандартном месте Laravel (`storage/logs/laravel.log`).
