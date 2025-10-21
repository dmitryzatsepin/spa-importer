# Bitrix24 API Service

Сервис для работы с REST API Битрикс24 в Laravel приложении.

## Установка

Все необходимые файлы уже созданы в директории `app/Services/Bitrix24/`.

## Использование

### Создание экземпляра сервиса

#### Простой вариант (без автоматического обновления токенов)

```php
use App\Services\Bitrix24\Bitrix24APIService;

$service = new Bitrix24APIService(
    domain: 'your-portal.bitrix24.ru',
    accessToken: 'your_access_token',
    timeout: 30,           // опционально, по умолчанию 30 секунд
    connectTimeout: 5      // опционально, по умолчанию 5 секунд
);
```

#### С автоматическим обновлением токенов (рекомендуется)

```php
use App\Services\Bitrix24\Bitrix24APIService;
use App\Models\Portal;

$portal = Portal::find($portalId);

$service = new Bitrix24APIService(
    domain: $portal->domain,
    accessToken: $portal->access_token,
    timeout: 30,
    connectTimeout: 5,
    portal: $portal        // передайте модель Portal для автообновления токенов
);

// Токены будут автоматически обновляться при необходимости!
$result = $service->call('crm.deal.list');
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

## Автоматическое обновление токенов

Сервис поддерживает автоматическое обновление токенов доступа. Токены Битрикс24 имеют ограниченный срок жизни (обычно 1 час).

### Как это работает

1. Передайте модель `Portal` в конструктор `Bitrix24APIService`
2. Перед каждым API-запросом сервис проверяет срок действия токена
3. Если токен истек или истекает в ближайшие 60 секунд, он автоматически обновляется
4. Новые токены сохраняются в БД
5. Исходный API-запрос выполняется с обновленным токеном

### Настройка

Убедитесь, что в `config/services.php` настроены credentials:

```php
'bitrix24' => [
    'client_id' => env('BITRIX24_CLIENT_ID'),
    'client_secret' => env('BITRIX24_CLIENT_SECRET'),
],
```

В `.env` файле:

```env
BITRIX24_CLIENT_ID=your_client_id
BITRIX24_CLIENT_SECRET=your_client_secret
```

### Пример использования

```php
use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Exceptions\TokenRefreshException;
use App\Models\Portal;

$portal = Portal::find($portalId);

try {
    $service = new Bitrix24APIService(
        $portal->domain,
        $portal->access_token,
        30,
        5,
        $portal  // Важно: передаем модель для автообновления
    );
    
    // Токен обновится автоматически, если необходимо
    $result = $service->call('crm.deal.list');
    
} catch (TokenRefreshException $e) {
    // Ошибка обновления токена (например, невалидный refresh_token)
    \Log::error('Token refresh failed', [
        'error' => $e->getMessage(),
        'context' => $e->getContext()
    ]);
    
    // Можно попросить пользователя переустановить приложение
    return redirect()->route('auth.install');
}
```

### Обработка ошибок

```php
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use App\Services\Bitrix24\Exceptions\TokenRefreshException;

try {
    $result = $service->call('some.method', $params);
    
} catch (TokenRefreshException $e) {
    // Ошибка обновления токена
    // Обычно означает, что refresh_token невалиден
    // или приложение было удалено из портала
    echo "Не удалось обновить токен: " . $e->getMessage();
    echo "Контекст: " . print_r($e->getContext(), true);
    
} catch (Bitrix24APIException $e) {
    // Обычная ошибка API
    $errorMessage = $e->getMessage();
    $context = $e->getContext();
    
    // В контексте может быть:
    // - method: метод API
    // - error: код ошибки от Битрикс24
    // - error_description: описание ошибки
    // - params: параметры запроса
    // - status: HTTP статус
    
    \Log::error('Bitrix24 API Error', [
        'message' => $errorMessage,
        'context' => $context
    ]);
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

### Тестовые маршруты

Для тестирования сервиса доступны специальные маршруты:

```http
GET /test-bitrix24/
GET /test-bitrix24/single?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/batch?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/error?domain=your-portal.bitrix24.ru&token=YOUR_TOKEN
GET /test-bitrix24/invalid-token?domain=your-portal.bitrix24.ru
GET /test-bitrix24/token-refresh?portal_id=1
```

### Консольный скрипт для тестирования обновления токенов

```bash
# Обычный запуск (проверка состояния токена)
php test-token-refresh.php

# Принудительно истечь токен и протестировать обновление
php test-token-refresh.php --expire
```

Скрипт покажет:

- Текущее состояние токена (истек ли он)
- Был ли токен обновлен автоматически
- Результат API-запроса

## Примеры использования в контроллерах

```php
namespace App\Http\Controllers;

use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Services\Bitrix24\Exceptions\TokenRefreshException;
use App\Models\Portal;

class DealController extends Controller
{
    public function index(Portal $portal)
    {
        try {
            // С автоматическим обновлением токенов
            $service = new Bitrix24APIService(
                $portal->domain,
                $portal->access_token,
                30,
                5,
                $portal  // передаем модель
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
            
        } catch (TokenRefreshException $e) {
            // Токен не удалось обновить - попросим переустановить
            return redirect()->route('auth.install')
                ->with('error', 'Необходимо переустановить приложение');
        }
    }
    
    public function batchUpdate(Portal $portal, array $dealIds)
    {
        $service = new Bitrix24APIService(
            $portal->domain,
            $portal->access_token,
            30,
            5,
            $portal
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

## Использование в фоновых задачах (Jobs)

Механизм автоматического обновления токенов особенно полезен в фоновых задачах:

```php
namespace App\Jobs;

use App\Models\Portal;
use App\Services\Bitrix24\Bitrix24APIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportDealsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Portal $portal;

    public function __construct(Portal $portal)
    {
        $this->portal = $portal;
    }

    public function handle()
    {
        // Токены обновятся автоматически, даже если задача
        // выполняется через несколько часов после создания
        $service = new Bitrix24APIService(
            $this->portal->domain,
            $this->portal->access_token,
            30,
            5,
            $this->portal
        );

        $start = 0;
        do {
            $result = $service->call('crm.deal.list', [
                'start' => $start,
                'select' => ['ID', 'TITLE', 'OPPORTUNITY']
            ]);

            // Обработка сделок...
            
            $start += 50;
        } while ($result['next'] ?? false);
    }
}
```

## Логирование

Все ошибки автоматически логируются с использованием Laravel Log facade. Логи сохраняются в стандартном месте Laravel (`storage/logs/laravel.log`).
