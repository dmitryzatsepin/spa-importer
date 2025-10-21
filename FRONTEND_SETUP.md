# Руководство по запуску фронтенда

## Быстрый старт

### 1. Установка зависимостей

```bash
npm install
```

### 2. Режим разработки

Запуск Vite dev-сервера с hot-reload:

```bash
npm run dev
```

Vite будет запущен на `http://localhost:5173/`

### 3. Сборка для продакшена

```bash
npm run build
```

Скомпилированные ассеты будут в директории `public/build/`

### 4. Запуск Laravel сервера

В отдельном терминале:

```bash
php artisan serve
```

Приложение доступно на `http://localhost:8000`

## Структура проекта

```text
resources/
├── js/
│   ├── main.tsx                 # Точка входа
│   ├── AppComponent.tsx         # Главный компонент
│   ├── components/              # React компоненты
│   │   ├── SmartProcessSelector.tsx
│   │   ├── FileUploader.tsx
│   │   ├── FieldMapper.tsx
│   │   └── ImportProgress.tsx
│   ├── services/
│   │   └── api.ts              # API клиент
│   └── types/
│       └── api.ts              # TypeScript типы
└── css/
    └── app.css                 # Стили приложения
```

## Доступ к приложению

После запуска Laravel сервера, откройте в браузере:

```text
http://localhost:8000/?portal_id=1
```

Параметры URL:

- `portal_id` - ID портала из таблицы `portals` (обязательный)
- `domain` - домен портала (опционально, для отображения)
- `member_id` - ID пользователя (опционально)

## Взаимодействие с API

Фронтенд использует следующие эндпоинты:

1. `GET /api/v1/smart-processes` - список смарт-процессов
2. `GET /api/v1/smart-processes/{id}/fields` - поля смарт-процесса
3. `POST /api/v1/import` - запуск импорта
4. `GET /api/v1/import/{jobId}/status` - статус импорта

## Требования

- Node.js >= 18
- npm >= 9
- PHP >= 8.2
- Laravel >= 11

## Примечания

- В режиме разработки используется Vite HMR (Hot Module Replacement)
- CSRF защита настроена автоматически через meta-тег
- Axios настроен в `resources/js/bootstrap.js`
