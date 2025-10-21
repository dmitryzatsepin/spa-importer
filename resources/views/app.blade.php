<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Импорт данных в Битрикс24</title>

    @vite(['resources/css/app.css', 'resources/js/main.tsx'])
</head>

<body>
    <div id="root"></div>

    {{-- Конфигурация приложения --}}
    <script id="app-config" type="application/json">
        {!! json_encode([
    'member_id' => $member_id ?? null,
    'domain' => $domain ?? null,
    'portal_id' => $portal_id ?? null,
]) !!}
    </script>
</body>

</html>