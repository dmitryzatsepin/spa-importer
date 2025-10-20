#!/bin/bash
# Быстрый тест Bitrix24 API на Linux/Mac
# Использование: ./test-local.sh YOUR_DOMAIN YOUR_TOKEN

if [ -z "$1" ]; then
    echo ""
    echo -e "\033[91mОшибка: не указан домен\033[0m"
    echo ""
    echo "Использование:"
    echo "  ./test-local.sh DOMAIN TOKEN"
    echo ""
    echo "Пример:"
    echo "  ./test-local.sh portal.bitrix24.ru 1/abcdef123456"
    echo ""
    echo "Как получить токен:"
    echo "  1. Откройте ваш портал Битрикс24"
    echo "  2. Настройки → Другое → Входящие вебхуки"
    echo "  3. Добавьте вебхук и скопируйте токен из URL"
    echo ""
    exit 1
fi

if [ -z "$2" ]; then
    echo ""
    echo -e "\033[91mОшибка: не указан токен\033[0m"
    echo ""
    echo "Использование:"
    echo "  ./test-local.sh $1 TOKEN"
    echo ""
    exit 1
fi

echo ""
echo "============================================"
echo "  Тестирование Bitrix24 API Service"
echo "============================================"
echo ""
echo "Домен: $1"
echo "Токен: $2"
echo ""

php quick-test-bitrix24.php "$1" "$2"

