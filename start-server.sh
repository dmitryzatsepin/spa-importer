#!/bin/bash

echo "========================================"
echo "Проверка выполнения задачи 1.1"
echo "========================================"
echo ""

echo "[1/7] Проверка версии Laravel..."
php artisan --version
echo ""

echo "[2/7] Проверка настроек БД в .env..."
grep "DB_CONNECTION" .env
grep "DB_DATABASE" .env
echo ""

echo "[3/7] Проверка файла базы данных..."
if [ -f database/database.sqlite ]; then
    echo "✓ Файл database.sqlite существует"
    ls -lh database/database.sqlite
else
    echo "✗ Файл database.sqlite НЕ найден"
fi
echo ""

echo "[4/7] Проверка Git репозитория..."
git log --oneline -3
echo ""

echo "[5/7] Проверка .gitignore..."
echo "Ищем исключения .cursor/, reference/, tasks/:"
grep -E "(\.cursor/|reference/|tasks/)" .gitignore
echo ""

echo "[6/7] Проверка зависимостей..."
if [ -f vendor/autoload.php ]; then
    echo "✓ Composer зависимости установлены"
    echo "  Автозагрузка классов доступна"
else
    echo "✗ Composer зависимости НЕ установлены"
    echo "  Выполните: composer install"
fi
echo ""

echo "[7/7] Проверка APP_KEY..."
APP_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
if [ -n "$APP_KEY" ] && [ "$APP_KEY" != "" ]; then
    echo "✓ APP_KEY сгенерирован"
else
    echo "✗ APP_KEY не установлен"
    echo "  Выполните: php artisan key:generate"
fi
echo ""

echo "========================================"
echo "Проверка завершена!"
echo "========================================"
echo ""
echo "Для запуска сервера выполните:"
echo "  php artisan serve"
echo ""
echo "Затем откройте в браузере:"
echo "  http://127.0.0.1:8000"
echo ""

