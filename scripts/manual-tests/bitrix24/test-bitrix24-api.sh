#!/bin/bash
# Обёртка для запуска test-bitrix24-api.php
# Использование: ./test-bitrix24-api.sh DOMAIN TOKEN

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/bitrix24/test-bitrix24-api.php "$@"

