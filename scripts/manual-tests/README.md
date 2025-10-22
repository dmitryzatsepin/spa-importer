# Тестовые скрипты для ручного тестирования

Коллекция скриптов для ручного тестирования компонентов системы импорта данных в Bitrix24.

## Структура директорий

```text
manual-tests/
  ├── bitrix24/        - Тесты Bitrix24 API и OAuth
  ├── api/             - Тесты REST API импорта
  ├── frontend/        - Тесты фронтенд интеграции
  ├── jobs/            - Тесты фоновых задач
  └── utils/           - Утилиты для отладки
```

## Bitrix24 тесты

### test-bitrix24-api

Комплексное тестирование Bitrix24 API Service.

**Использование:**

```bash
cd spa-importer

# Через .sh скрипт
./scripts/manual-tests/bitrix24/test-bitrix24-api.sh DOMAIN TOKEN

# Напрямую через PHP
php scripts/manual-tests/bitrix24/test-bitrix24-api.php DOMAIN TOKEN
```

**Примеры:**

```bash
# С вебхуком
./scripts/manual-tests/bitrix24/test-bitrix24-api.sh portal.bitrix24.ru 1/abcdef123456

# С OAuth токеном
./scripts/manual-tests/bitrix24/test-bitrix24-api.sh portal.bitrix24.ru your_oauth_token
```

**Что тестирует:**

- Подключение к API
- Методы app.info, user.current, crm.deal.list
- Пакетные запросы (batch)
- Обработка ошибок

### test-token-refresh

Тестирование автоматического обновления OAuth токенов.

**Использование:**

```bash
cd spa-importer

# Обычный тест
./scripts/manual-tests/bitrix24/test-token-refresh.sh

# С принудительным истечением токена
./scripts/manual-tests/bitrix24/test-token-refresh.sh --expire
```

**Требования:**

- Портал должен быть настроен в БД
- Необходимы валидные access_token и refresh_token

### test-local

Обёртка для быстрого запуска test-bitrix24-api с подсказками.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/bitrix24/test-local.sh DOMAIN TOKEN
```

## API тесты

### test-api-switch

Тестирование переключения между мок и реальным API.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/api/test-api-switch.sh
```

**Что проверяет:**

- Работу мок режима (API_USE_MOCK=true)
- Работу реального режима (API_USE_MOCK=false)
- Конфигурацию Laravel

### test-import-api

Базовая проверка готовности API импорта.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/api/test-import-api.sh
```

**Что проверяет:**

- Подключение к БД
- Наличие портала
- Директорию для импорта
- Модель ImportJob
- Зарегистрированные маршруты

### test-import-api-full

Полный цикл импорта через API с мониторингом прогресса.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/api/test-import-api-full.sh
```

**Что делает:**

1. Создаёт тестовый CSV файл (5 строк)
2. Отправляет POST запрос на /api/import/start
3. Мониторит прогресс импорта каждые 2 секунды
4. Выводит результат (completed/failed)
5. Удаляет временный файл

**Требования:**

- Запущенный Laravel сервер (php artisan serve)
- Настроенный портал в БД
- Работающий queue worker (php artisan queue:work)

## Frontend тесты

### test-frontend-api

Проверка интеграции фронтенда с API.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/frontend/test-frontend-api.sh
```

**Требования:**

- Запущенный Laravel сервер
- Запущенный Vite dev-сервер (npm run dev)

**Что проверяет:**

- Доступность API эндпоинтов
- Главную страницу (наличие React контейнера)
- Подключение Vite ассетов
- Наличие портала в БД

## Jobs тесты

### test-import-job

Тестирование ProcessImportJob с реальными данными.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/jobs/test-import-job.sh
```

**Интерактивный режим:**

- Выбор синхронного или асинхронного выполнения
- Создание тестового CSV с 3 строками
- Настройка field mappings
- Запуск и мониторинг

**Требования:**

- Портал в БД с валидным токеном
- Для асинхронного режима: queue worker

## Утилиты

### show-tables

Просмотр таблиц в базе данных.

**Использование:**

```bash
cd spa-importer
./scripts/manual-tests/utils/show-tables.sh
```

**Вывод:**

- Список всех таблиц в БД
- Количество таблиц

## Общие требования

### Для всех скриптов

- PHP 8.1+
- Composer зависимости установлены
- .env файл настроен

### Для API и Jobs тестов

- Настроенная база данных (SQLite)
- Выполненные миграции
- Хотя бы один портал в таблице `portals`

### Для тестов с реальным Bitrix24

- Валидный портал с OAuth токенами
- Или настроенный вебхук

## Запуск всех тестов

Рекомендуемая последовательность для полной проверки:

```bash
cd spa-importer

# 1. Проверка базовых компонентов
./scripts/manual-tests/utils/show-tables.sh
./scripts/manual-tests/api/test-import-api.sh

# 2. Проверка Bitrix24 (если есть токен)
./scripts/manual-tests/bitrix24/test-bitrix24-api.sh DOMAIN TOKEN
./scripts/manual-tests/bitrix24/test-token-refresh.sh

# 3. Проверка API импорта
./scripts/manual-tests/api/test-api-switch.sh

# 4. Запуск серверов в отдельных терминалах
php artisan serve          # Терминал 1
npm run dev                # Терминал 2
php artisan queue:work     # Терминал 3

# 5. Проверка фронтенда
./scripts/manual-tests/frontend/test-frontend-api.sh

# 6. Полный цикл импорта
./scripts/manual-tests/api/test-import-api-full.sh
```

## Поиск проблем

### Скрипт не запускается

```bash
# Проверьте права на выполнение
chmod +x scripts/manual-tests/**/*.sh

# Проверьте текущую директорию
pwd  # Должно быть: .../spa-importer
```

### Ошибки подключения к БД

```bash
# Проверьте наличие файла БД
ls -la database/database.sqlite

# Выполните миграции
php artisan migrate
```

### API возвращает 404

```bash
# Проверьте маршруты
php artisan route:list | grep api

# Очистите кеш
php artisan config:clear
php artisan cache:clear
```

### Ошибки Bitrix24 API

```bash
# Проверьте токен
php artisan tinker
>>> App\Models\Portal::first()

# Проверьте срок действия
>>> App\Models\Portal::first()->isTokenExpired()
```

## Логи

Все скрипты используют Laravel логирование:

```bash
# Просмотр логов в реальном времени
tail -f storage/logs/laravel.log

# Очистка логов
> storage/logs/laravel.log
```

## Дополнительно

### Серверные скрипты

```bash
# Быстрый запуск всех серверов
../scripts/server/start-server.sh
```

### Создание тестовых данных

Используйте `test-import-api-full.sh` - он автоматически создаёт тестовый CSV файл.

### Отладка

Для детальной отладки добавьте в .env:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```
