#!/bin/bash

# Тестовый скрипт для полного цикла импорта через API
# Использование: bash test-import-api-full.sh

echo "=== Тест полного цикла импорта через API ==="
echo ""

# Настройки
API_URL="http://localhost:8000/api"
PORTAL_ID=1

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Создаем тестовый CSV файл
echo "1. Создание тестового CSV файла..."

cat > test_import.csv << 'EOF'
Название;Дата создания;Ответственный;Сумма;Активен
Тестовый элемент 1;01.01.2024;1;1000.50;Да
Тестовый элемент 2;15.02.2024;1;2500;Нет
Тестовый элемент 3;20.03.2024;1;3750.75;Да
Тестовый элемент 4;05.04.2024;1;5000;Да
Тестовый элемент 5;10.05.2024;1;7500.25;Нет
EOF

echo -e "${GREEN}✓${NC} Файл test_import.csv создан"
echo ""

# 2. Запускаем импорт
echo "2. Запуск импорта через API..."

# JSON для field_mappings
FIELD_MAPPINGS='[
  {"source_column": "Название", "target_field": "TITLE"},
  {"source_column": "Дата создания", "target_field": "CREATED_DATE", "transform": "date", "date_format": "d.m.Y"},
  {"source_column": "Ответственный", "target_field": "ASSIGNED_BY_ID", "transform": "user"},
  {"source_column": "Сумма", "target_field": "OPPORTUNITY", "transform": "number"},
  {"source_column": "Активен", "target_field": "IS_ACTIVE", "transform": "boolean"}
]'

# JSON для settings (замените entity_type_id на ID вашего смарт-процесса)
SETTINGS='{
  "entity_type_id": 128,
  "duplicate_handling": "skip",
  "batch_size": 10
}'

RESPONSE=$(curl -s -X POST "${API_URL}/import/start" \
  -F "file=@test_import.csv" \
  -F "portal_id=${PORTAL_ID}" \
  -F "field_mappings=${FIELD_MAPPINGS}" \
  -F "settings=${SETTINGS}")

echo "Ответ API:"
echo "$RESPONSE" | jq '.'

# Извлекаем job_id
JOB_ID=$(echo "$RESPONSE" | jq -r '.data.job_id')

if [ "$JOB_ID" == "null" ] || [ -z "$JOB_ID" ]; then
    echo -e "${RED}✗${NC} Не удалось создать задачу импорта"
    echo "Ответ сервера: $RESPONSE"
    exit 1
fi

echo -e "${GREEN}✓${NC} Задача создана ID: ${JOB_ID}"
echo ""

# 3. Мониторинг прогресса
echo "3. Мониторинг прогресса импорта..."
echo "   (проверка каждые 2 секунды)"
echo ""

MAX_ITERATIONS=60
ITERATION=0

while [ $ITERATION -lt $MAX_ITERATIONS ]; do
    ITERATION=$((ITERATION + 1))
    
    STATUS_RESPONSE=$(curl -s "${API_URL}/import/${JOB_ID}/status")
    
    STATUS=$(echo "$STATUS_RESPONSE" | jq -r '.data.status')
    TOTAL_ROWS=$(echo "$STATUS_RESPONSE" | jq -r '.data.total_rows')
    PROCESSED_ROWS=$(echo "$STATUS_RESPONSE" | jq -r '.data.processed_rows')
    PROGRESS=$(echo "$STATUS_RESPONSE" | jq -r '.data.progress_percentage')
    
    echo -ne "\r   Статус: ${STATUS} | Обработано: ${PROCESSED_ROWS}/${TOTAL_ROWS} | Прогресс: ${PROGRESS}%   "
    
    # Проверяем финальные статусы
    if [ "$STATUS" == "completed" ]; then
        echo ""
        echo -e "${GREEN}✓${NC} Импорт успешно завершен!"
        echo ""
        echo "Полная информация:"
        echo "$STATUS_RESPONSE" | jq '.data'
        break
    elif [ "$STATUS" == "failed" ]; then
        echo ""
        echo -e "${RED}✗${NC} Импорт завершен с ошибкой"
        echo ""
        echo "Детали ошибки:"
        echo "$STATUS_RESPONSE" | jq '.data.error_details'
        break
    fi
    
    sleep 2
done

if [ $ITERATION -eq $MAX_ITERATIONS ]; then
    echo ""
    echo -e "${YELLOW}⚠${NC} Таймаут ожидания завершения импорта"
    echo "   Последний статус: $STATUS"
    echo "   Проверьте статус вручную:"
    echo "   curl ${API_URL}/import/${JOB_ID}/status | jq"
fi

echo ""

# 4. Очистка
echo "4. Очистка временных файлов..."
rm -f test_import.csv
echo -e "${GREEN}✓${NC} Очистка завершена"

echo ""
echo "=== Тест завершен ==="
echo ""
echo "Полезные команды:"
echo "  - Проверить статус: curl ${API_URL}/import/${JOB_ID}/status | jq"
echo "  - Запустить воркер: php artisan queue:work"
echo "  - Посмотреть логи: tail -f storage/logs/laravel.log"

