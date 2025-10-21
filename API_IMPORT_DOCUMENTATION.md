# API документация: Импорт данных

## Обзор

API для импорта данных из CSV/Excel файлов в смарт-процессы Битрикс24. Поддерживает асинхронную обработку, отслеживание прогресса и гибкую настройку сопоставления полей.

**Base URL:** `/api/v1`

---

## Эндпоинты

### 1. Получить список смарт-процессов

Возвращает список доступных смарт-процессов с портала Битрикс24.

**Endpoint:** `GET /api/v1/smart-processes`

**Query параметры:**

- `portal_id` (required, integer) - ID портала из таблицы `portals`

**Пример запроса:**

```http
GET /api/v1/smart-processes?portal_id=1
```

**Успешный ответ (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 128,
      "title": "Проекты",
      "code": "projects"
    },
    {
      "id": 130,
      "title": "Задачи",
      "code": "tasks"
    }
  ]
}
```

**Ошибка (500):**

```json
{
  "success": false,
  "message": "Не удалось получить список смарт-процессов",
  "error": "Детальное описание ошибки"
}
```

---

### 2. Получить поля смарт-процесса

Возвращает список полей конкретного смарт-процесса для настройки сопоставления.

**Endpoint:** `GET /api/v1/smart-processes/{entityTypeId}/fields`

**URL параметры:**

- `entityTypeId` (required, integer) - ID типа сущности (смарт-процесса)

**Query параметры:**

- `portal_id` (required, integer) - ID портала

**Пример запроса:**

```http
GET /api/v1/smart-processes/128/fields?portal_id=1
```

**Успешный ответ (200):**

```json
{
  "success": true,
  "data": [
    {
      "code": "TITLE",
      "title": "Название",
      "type": "string",
      "isRequired": true,
      "isReadOnly": false
    },
    {
      "code": "ASSIGNED_BY_ID",
      "title": "Ответственный",
      "type": "user",
      "isRequired": false,
      "isReadOnly": false
    }
  ]
}
```

---

### 3. Запустить импорт

Создает задачу импорта и ставит её в очередь на асинхронную обработку.

**Endpoint:** `POST /api/v1/import`

**Content-Type:** `multipart/form-data`

**Параметры:**

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `file` | File | Да | CSV/XLSX/XLS файл (макс. 10 МБ) |
| `portal_id` | Integer | Да | ID портала |
| `entity_type_id` | Integer | Да | ID смарт-процесса |
| `field_mappings` | Array | Да | Массив сопоставления полей |
| `field_mappings[].source` | String | Да | Название колонки в файле |
| `field_mappings[].target` | String | Да | Код поля в Битрикс24 |
| `settings` | Object | Нет | Дополнительные настройки |
| `settings.duplicate_handling` | String | Нет | `skip`, `update`, `create_new` |
| `settings.duplicate_field` | String | Нет | Поле для проверки дубликатов |
| `settings.batch_size` | Integer | Нет | Размер пакета (1-50) |

**Пример запроса (curl):**

```bash
curl -X POST "http://localhost:8000/api/v1/import" \
  -F "file=@data.csv" \
  -F "portal_id=1" \
  -F "entity_type_id=128" \
  -F "field_mappings[0][source]=Название" \
  -F "field_mappings[0][target]=TITLE" \
  -F "field_mappings[1][source]=Ответственный" \
  -F "field_mappings[1][target]=ASSIGNED_BY_ID" \
  -F "settings[duplicate_handling]=skip" \
  -F "settings[batch_size]=10"
```

**Пример тела (JSON формат для понимания структуры):**

```json
{
  "portal_id": 1,
  "entity_type_id": 128,
  "field_mappings": [
    {
      "source": "Название",
      "target": "TITLE"
    },
    {
      "source": "Ответственный",
      "target": "ASSIGNED_BY_ID"
    }
  ],
  "settings": {
    "duplicate_handling": "skip",
    "duplicate_field": "TITLE",
    "batch_size": 10
  }
}
```

**Успешный ответ (202 Accepted):**

```json
{
  "success": true,
  "message": "Задача импорта создана",
  "data": {
    "job_id": 15
  }
}
```

**Ошибка валидации (422):**

```json
{
  "message": "The file field is required. (and 1 more error)",
  "errors": {
    "file": [
      "Необходимо загрузить файл для импорта"
    ],
    "entity_type_id": [
      "Необходимо указать ID смарт-процесса"
    ]
  }
}
```

---

### 4. Получить статус импорта

Возвращает текущее состояние задачи импорта, включая прогресс и ошибки.

**Endpoint:** `GET /api/v1/import/{jobId}/status`

**URL параметры:**

- `jobId` (required, integer) - ID задачи импорта

**Пример запроса:**

```http
GET /api/v1/import/15/status
```

**Успешный ответ (200):**

```json
{
  "success": true,
  "data": {
    "job_id": 15,
    "status": "processing",
    "original_filename": "data.csv",
    "total_rows": 100,
    "processed_rows": 45,
    "progress_percentage": 45.0,
    "error_details": null,
    "created_at": "2025-10-21T10:30:00.000000Z",
    "updated_at": "2025-10-21T10:31:15.000000Z"
  }
}
```

**Возможные статусы:**

- `pending` - задача создана, ожидает обработки
- `processing` - задача обрабатывается
- `completed` - импорт завершен успешно
- `failed` - импорт завершен с ошибками

**Ошибка (404):**

```json
{
  "success": false,
  "message": "Не удалось получить статус задачи импорта",
  "error": "No query results for model [App\\Models\\ImportJob] 999"
}
```

---

## Модели данных

### ImportJob

```php
[
    'id' => 15,
    'portal_id' => 1,
    'status' => 'processing',
    'original_filename' => 'data.csv',
    'stored_filepath' => 'imports/1729508400_67123abc_data.csv',
    'field_mappings' => [
        ['source' => 'Название', 'target' => 'TITLE'],
        ['source' => 'Ответственный', 'target' => 'ASSIGNED_BY_ID']
    ],
    'settings' => [
        'entity_type_id' => 128,
        'duplicate_handling' => 'skip',
        'batch_size' => 10
    ],
    'total_rows' => 100,
    'processed_rows' => 45,
    'error_details' => null,
    'created_at' => '2025-10-21T10:30:00.000000Z',
    'updated_at' => '2025-10-21T10:31:15.000000Z'
]
```

---

## Коды ответов

| Код | Значение | Описание |
|-----|----------|----------|
| 200 | OK | Успешный запрос |
| 202 | Accepted | Задача принята на обработку |
| 404 | Not Found | Ресурс не найден |
| 422 | Unprocessable Entity | Ошибка валидации данных |
| 500 | Internal Server Error | Ошибка сервера |

---

## Обработка ошибок

Все эндпоинты возвращают единый формат ошибок:

```json
{
  "success": false,
  "message": "Понятное описание ошибки",
  "error": "Техническое описание (опционально)"
}
```

Ошибки также логируются в `storage/logs/laravel.log` с контекстом для отладки.

---

## Примеры использования

### JavaScript (Fetch API)

```javascript
// Запуск импорта
async function startImport(file, portalId, entityTypeId, fieldMappings) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('portal_id', portalId);
  formData.append('entity_type_id', entityTypeId);

  fieldMappings.forEach((mapping, index) => {
    formData.append(`field_mappings[${index}][source]`, mapping.source);
    formData.append(`field_mappings[${index}][target]`, mapping.target);
  });

  const response = await fetch('/api/v1/import', {
    method: 'POST',
    body: formData
  });

  return await response.json();
}

// Отслеживание прогресса
async function checkProgress(jobId) {
  const response = await fetch(`/api/v1/import/${jobId}/status`);
  return await response.json();
}

// Использование
const result = await startImport(
  fileInput.files[0],
  1,
  128,
  [
    { source: 'Название', target: 'TITLE' },
    { source: 'Ответственный', target: 'ASSIGNED_BY_ID' }
  ]
);

console.log('Job ID:', result.data.job_id);

// Опрос статуса каждые 2 секунды
const interval = setInterval(async () => {
  const status = await checkProgress(result.data.job_id);
  console.log('Progress:', status.data.progress_percentage + '%');

  if (status.data.status === 'completed' || status.data.status === 'failed') {
    clearInterval(interval);
  }
}, 2000);
```

---

## Тестирование

Для быстрого тестирования используйте скрипт:

```bash
php test-import-api.php
```

Или проверьте маршруты:

```bash
php artisan route:list --path=api/v1
```

---

## Безопасность

⚠️ **Важно:** В текущей версии API не защищен middleware для аутентификации.

Для продакшена необходимо добавить:

- Laravel Sanctum или Passport для API-токенов
- Rate limiting для предотвращения злоупотреблений
- Валидацию прав доступа к порталу

---

## Что дальше?

Следующий шаг: реализация `ProcessImportJob` (задача 3.2) для асинхронной обработки загруженных файлов и отправки данных в Битрикс24.
