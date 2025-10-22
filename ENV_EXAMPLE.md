# Пример конфигурации .env

## API Mock Mode

Добавьте следующие переменные в ваш `.env` файл:

```env
# API Mock Mode
# Включает мок-контроллеры для тестирования фронтенда без реального API Битрикс24
# true - используются MockImportController (мок данные)
# false - используются ImportController (реальное API)
API_USE_MOCK=true
```

## Переключение между мок и реальным API

### Мок режим (по умолчанию)

```env
API_USE_MOCK=true
```

В этом режиме все API маршруты будут использовать `MockImportController`, который возвращает заранее подготовленные данные без реальных запросов к Битрикс24.

### Реальный API режим

```env
API_USE_MOCK=false
```

В этом режиме используется `ImportController` для работы с реальным API Битрикс24. Убедитесь, что у вас настроены:

- `BITRIX24_CLIENT_ID`
- `BITRIX24_CLIENT_SECRET`
- `BITRIX24_REDIRECT_URI`

## Полный пример .env файла

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

# API Mock Mode
API_USE_MOCK=true

# Bitrix24 OAuth
BITRIX24_CLIENT_ID=
BITRIX24_CLIENT_SECRET=
BITRIX24_REDIRECT_URI=

DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120

QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=stack
LOG_LEVEL=debug
```

## После изменения

После изменения значения `API_USE_MOCK` в `.env` файле:

1. Очистите кэш конфигурации: `php artisan config:clear`
2. Опционально, перезапустите сервер разработки
