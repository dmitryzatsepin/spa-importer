#!/bin/bash
# Обёртка для запуска test-token-refresh.php
# Использование: ./test-token-refresh.sh [--expire]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/bitrix24/test-token-refresh.php "$@"


