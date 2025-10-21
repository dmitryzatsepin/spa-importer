# ProcessImportJob - Документация

Фоновая задача для импорта данных из Excel/CSV в Битрикс24 смарт-процессы.

## Возможности

- ✅ Поддержка форматов: XLSX, XLS, CSV
- ✅ Автоопределение разделителей и кодировки CSV
- ✅ Преобразование дат, чисел, булевых значений
- ✅ Проверка дубликатов
- ✅ Батчинг запросов к Битрикс24
- ✅ Отслеживание прогресса в реальном времени
- ✅ Детальное логирование ошибок
- ✅ Автоматическое обновление токенов
- ✅ Обработка больших файлов

## Установка

```bash
# 1. Установить зависимости
cd spa-importer
composer install

# 2. Настроить .env
cp .env.example .env
php artisan key:generate

# 3. Настроить БД
php artisan migrate

# 4. Настроить Битрикс24 credentials
# Добавить в .env:
BITRIX24_CLIENT_ID=your_client_id
BITRIX24_CLIENT_SECRET=your_client_secret

# 5. Настроить очереди
# В .env установить:
QUEUE_CONNECTION=database

# 6. Запустить воркер
php artisan queue:work
```

## Быстрый старт

### 1. Подготовить файл импорта

Пример: import.csv

```csv
Название;Дата создания;Ответственный;Сумма;Активен
Элемент 1;01.01.2024;1;1000.50;Да
Элемент 2;15.02.2024;2;2500;Нет
```

### 2. Запустить импорт через API

```bash
curl -X POST http://localhost:8000/api/import/start \
  -F "file=@import.csv" \
  -F "portal_id=1" \
  -F 'field_mappings=[
    {"source_column": "Название", "target_field": "TITLE"},
    {"source_column": "Дата создания", "target_field": "CREATED_DATE", "transform": "date", "date_format": "d.m.Y"},
    {"source_column": "Ответственный", "target_field": "ASSIGNED_BY_ID", "transform": "user"},
    {"source_column": "Сумма", "target_field": "OPPORTUNITY", "transform": "number"},
    {"source_column": "Активен", "target_field": "IS_ACTIVE", "transform": "boolean"}
  ]' \
  -F 'settings={"entity_type_id": 128, "duplicate_handling": "skip", "batch_size": 10}'
```

Ответ:

```json
{
  "success": true,
  "message": "Задача импорта создана",
  "data": {
    "job_id": 1
  }
}
```

### 3. Проверить статус

```bash
curl http://localhost:8000/api/import/1/status
```

Ответ:

```json
{
  "success": true,
  "data": {
    "job_id": 1,
    "status": "processing",
    "original_filename": "import.csv",
    "total_rows": 2,
    "processed_rows": 1,
    "progress_percentage": 50.00,
    "error_details": null,
    "created_at": "2025-10-21T10:00:00",
    "updated_at": "2025-10-21T10:00:15"
  }
}
```

## Форматы данных

### field_mappings

Маппинг столбцов файла на поля Битрикс24:

```json
[
  {
    "source_column": "Название столбца",
    "target_field": "FIELD_CODE",
    "transform": "тип преобразования (опционально)",
    "параметры преобразования": "значения"
  }
]
```

#### Типы преобразований

#### 1. date - Даты

```json
{
  "source_column": "Дата",
  "target_field": "CREATED_DATE",
  "transform": "date",
  "date_format": "d.m.Y"
}
```

Поддерживаемые форматы:

- `d.m.Y` → 01.12.2024
- `Y-m-d` → 2024-12-01
- `m/d/Y` → 12/01/2024
- Excel serial numbers (автоматически)
- Любой формат, понимаемый `strtotime()`

#### 2. datetime - Дата и время

```json
{
  "source_column": "Дата и время",
  "target_field": "UPDATED_TIME",
  "transform": "datetime",
  "datetime_format": "d.m.Y H:i:s"
}
```

#### 3. user - Пользователь

```json
{
  "source_column": "Ответственный",
  "target_field": "ASSIGNED_BY_ID",
  "transform": "user"
}
```

Принимает:

- ID пользователя (число)

#### 4. boolean - Булевы значения

```json
{
  "source_column": "Активен",
  "target_field": "IS_ACTIVE",
  "transform": "boolean"
}
```

Преобразует в формат Битрикс24 (`Y`/`N`):

- `Y`: 1, true, yes, да, y, +
- `N`: 0, false, no, нет, n, -

#### 5. number - Числа

```json
{
  "source_column": "Сумма",
  "target_field": "OPPORTUNITY",
  "transform": "number"
}
```

Поддерживает:

- Целые числа
- Десятичные (с точкой или запятой)
- С пробелами (1 000,50)

#### 6. crm_entity - CRM сущности

```json
{
  "source_column": "Контакт",
  "target_field": "CONTACT_ID",
  "transform": "crm_entity",
  "entity_type": "CONTACT"
}
```

Преобразует ID в формат `ENTITY_TYPE_ID`.

### settings

Настройки импорта:

```json
{
  "entity_type_id": 128,
  "duplicate_handling": "skip",
  "duplicate_check_field": "TITLE",
  "batch_size": 10
}
```

Параметры:

- `entity_type_id` (обязательно) - ID смарт-процесса в Битрикс24
- `duplicate_handling` - Обработка дубликатов:
  - `skip` - Пропустить (по умолчанию)
  - `update` - Обновить (в разработке)
- `duplicate_check_field` - Поле для проверки дубликатов (например, `TITLE`)
- `batch_size` - Размер батча (по умолчанию 10, максимум 50)

## Тестирование

### Тест 1: Простой PHP скрипт

```bash
php test-import-job.php
```

Интерактивный тест с возможностью:

- Синхронного выполнения (для отладки)
- Асинхронного через очередь

### Тест 2: Полный цикл через API

```bash
bash test-import-api-full.sh
```

Автоматически:

1. Создает тестовый CSV
2. Отправляет через API
3. Мониторит прогресс
4. Показывает результат

### Тест 3: Ручной через cURL

Шаг 1: Создать тестовый файл

```bash
cat > test.csv << 'EOF'
Название;Дата;Сумма
Тест 1;01.01.2024;1000
Тест 2;02.01.2024;2000
EOF
```

Шаг 2: Отправить импорт

```bash
curl -X POST http://localhost:8000/api/import/start \
  -F "file=@test.csv" \
  -F "portal_id=1" \
  -F 'field_mappings=[{"source_column": "Название", "target_field": "TITLE"}]' \
  -F 'settings={"entity_type_id": 128}'
```

Шаг 3: Проверить статус

```bash
curl http://localhost:8000/api/import/1/status | jq
```

### Тест 4: Запуск из кода

```php
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;

$importJob = ImportJob::find(1);

// Синхронно
$job = new ProcessImportJob($importJob->id);
$job->handle();

// Асинхронно
dispatch(new ProcessImportJob($importJob->id));
```

## Мониторинг

### Проверка статуса через API

```bash
# Получить статус
curl http://localhost:8000/api/import/{job_id}/status

# Непрерывный мониторинг
watch -n 2 "curl -s http://localhost:8000/api/import/1/status | jq '.data | {status, processed_rows, total_rows, progress_percentage}'"
```

### Проверка логов

```bash
# В реальном времени
tail -f storage/logs/laravel.log

# Фильтр по job_id
tail -f storage/logs/laravel.log | grep "job_id\":1"

# Только ошибки
tail -f storage/logs/laravel.log | grep "ERROR"
```

### Проверка очереди

```bash
# Список задач в очереди
php artisan queue:work --once

# Мониторинг воркера
php artisan queue:work --verbose

# Неудачные задачи
php artisan queue:failed
```

### Проверка БД

```sql
-- Статус всех импортов
SELECT id, status, original_filename, total_rows, processed_rows, created_at
FROM import_jobs
ORDER BY created_at DESC;

-- Импорт с ошибками
SELECT id, status, error_details
FROM import_jobs
WHERE status = 'failed';

-- Текущий прогресс
SELECT 
  id,
  status,
  CONCAT(processed_rows, '/', total_rows) as progress,
  ROUND((processed_rows / total_rows) * 100, 2) as percentage
FROM import_jobs
WHERE status = 'processing';
```

## Обработка ошибок

### Типы ошибок

#### 1. Ошибки файла

```json
{
  "error": "Файл импорта не найден: /path/to/file.xlsx",
  "file": "/app/Jobs/ProcessImportJob.php",
  "line": 95
}
```

#### 2. Ошибки строки

```json
[
  {
    "row": 15,
    "error": "Не удалось преобразовать дату: invalid format"
  }
]
```

#### 3. Ошибки API

```json
[
  {
    "command": "row_42",
    "error": "Required field TITLE is missing"
  }
]
```

#### 4. Ошибки батча

```json
[
  {
    "batch": "execution_failed",
    "error": "Превышен лимит запросов API"
  }
]
```

### Стратегии восстановления

Повтор неудачной задачи:

```php
$importJob = ImportJob::find($jobId);
$importJob->status = 'pending';
$importJob->processed_rows = 0;
$importJob->error_details = null;
$importJob->save();

dispatch(new ProcessImportJob($jobId));
```

Обработка с определенной строки:

```php
// Изменить в ProcessImportJob.php
protected function processRows(...) {
    $skipRows = $importJob->processed_rows;
    
    foreach (array_slice($rows, $skipRows) as $rowIndex => $row) {
        // ...
    }
}
```

## Оптимизация производительности

### Настройки для больших файлов

#### 1. Увеличить memory_limit

```ini
; php.ini
memory_limit = 512M
```

#### 2. Увеличить timeout

```php
// В ProcessImportJob.php
public $timeout = 3600; // 1 час
```

#### 3. Оптимизировать batch_size

```json
{
  "batch_size": 25
}
```

Рекомендации:

- Маленькие файлы (< 1000 строк): 10-15
- Средние файлы (1000-10000): 20-30
- Большие файлы (> 10000): 30-50

#### 4. Реже обновлять прогресс

```php
// В ProcessImportJob.php
protected int $progressUpdateInterval = 500;
```

### Производительность

Реальные показатели:

- 100 строк: ~10 секунд
- 1,000 строк: ~1.5 минуты
- 10,000 строк: ~15 минут
- 100,000 строк: ~2.5 часа

(Зависит от количества полей, трансформаций и скорости API Битрикс24)

## Решение проблем

### Задача не запускается

Проверить:

```bash
# Воркер запущен?
ps aux | grep queue:work

# Есть ли задачи в очереди?
SELECT * FROM jobs;

# Есть ли неудачные задачи?
php artisan queue:failed
```

Решение:

```bash
# Запустить воркер
php artisan queue:work

# Перезапустить неудачные задачи
php artisan queue:retry all
```

### Задача зависла

Проверить:

```bash
# Логи
tail -100 storage/logs/laravel.log

# Статус в БД
SELECT status, updated_at FROM import_jobs WHERE id = X;
```

Решение:

```bash
# Убить воркер
pkill -f "queue:work"

# Очистить зависшие задачи
DELETE FROM jobs WHERE attempts >= 3;

# Перезапустить
php artisan queue:work
```

### Ошибки преобразования данных

Проверить:

- Формат даты в `date_format` совпадает с файлом
- ID пользователей существуют в портале
- Числовые поля не содержат текст

Решение:

- Изменить маппинг
- Очистить данные в файле
- Использовать transform только для корректных данных

### Ошибки API Битрикс24

Типичные ошибки:

- `Required field X is missing` - Не заполнено обязательное поле
- `Invalid field value` - Неверный формат значения
- `Entity type not found` - Неверный entity_type_id
- `Access denied` - Нет прав доступа

Решение:

- Проверить маппинг полей
- Проверить права портала
- Проверить entity_type_id

## API Endpoints

### POST /api/import/start

Создать задачу импорта.

Parameters:

- `file` (file, required) - XLSX/XLS/CSV файл
- `portal_id` (int, required) - ID портала
- `field_mappings` (json, required) - Маппинг полей
- `settings` (json, required) - Настройки импорта

Response:

```json
{
  "success": true,
  "message": "Задача импорта создана",
  "data": {
    "job_id": 1
  }
}
```

### GET /api/import/{jobId}/status

Получить статус импорта.

Response:

```json
{
  "success": true,
  "data": {
    "job_id": 1,
    "status": "processing",
    "original_filename": "import.csv",
    "total_rows": 100,
    "processed_rows": 45,
    "progress_percentage": 45.00,
    "error_details": null,
    "created_at": "2025-10-21T10:00:00",
    "updated_at": "2025-10-21T10:01:30"
  }
}
```

## Архитектура

```text
startImport (API)
    ↓
ImportJob создан
    ↓
dispatch(ProcessImportJob)
    ↓
Queue Worker
    ↓
ProcessImportJob::handle()
    ├─ Загрузка ImportJob
    ├─ Установка статуса: processing
    ├─ Инициализация Bitrix24APIService
    ├─ Определение формата файла
    ├─ Чтение файла
    │   ├─ Excel: PHPSpreadsheet
    │   └─ CSV: CSV Reader
    ├─ Обработка строк
    │   ├─ Трансформация данных
    │   ├─ Проверка дубликатов
    │   ├─ Формирование batch
    │   ├─ Отправка в Битрикс24
    │   └─ Обновление прогресса
    └─ Финальный статус: completed/failed
```

## Расширение функциональности

### Добавить новый тип преобразования

```php
// В ProcessImportJob.php

protected function applyTransform($value, ?string $transform, array $mapping)
{
    switch ($transform) {
        // ... существующие типы ...
        
        case 'email':
            return $this->transformEmail($value);
            
        case 'phone':
            return $this->transformPhone($value, $mapping);
    }
}

protected function transformEmail($value): ?string
{
    $value = trim(strtolower($value));
    
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return $value;
    }
    
    return null;
}

protected function transformPhone($value, array $mapping): ?string
{
    // Очистка от всего кроме цифр и +
    $phone = preg_replace('/[^0-9+]/', '', $value);
    
    // Добавление кода страны если нужно
    $countryCode = $mapping['country_code'] ?? '+7';
    
    if (!str_starts_with($phone, '+')) {
        $phone = $countryCode . $phone;
    }
    
    return $phone;
}
```

### Добавить валидацию строк

```php
protected function processRows(...)
{
    foreach ($rows as $rowIndex => $row) {
        try {
            $fields = $this->transformRowToFields($row, $headers, $importJob->field_mappings);
            
            // Валидация
            if (!$this->validateFields($fields, $importJob->settings)) {
                $errors[] = [
                    'row' => $rowIndex + 2,
                    'error' => 'Validation failed',
                ];
                continue;
            }
            
            // ... остальная логика
        }
    }
}

protected function validateFields(array $fields, array $settings): bool
{
    $requiredFields = $settings['required_fields'] ?? [];
    
    foreach ($requiredFields as $field) {
        if (empty($fields[$field])) {
            Log::warning("Required field missing: {$field}");
            return false;
        }
    }
    
    return true;
}
```

## Лицензия

MIT

## Поддержка

При возникновении проблем:

1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте очередь: `php artisan queue:failed`
3. Проверьте БД: таблица `import_jobs`
4. Включите подробное логирование: `LOG_LEVEL=debug` в `.env`
