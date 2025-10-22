#!/bin/bash
# Обёртка для запуска test-import-api.php
# Использование: ./test-import-api.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/api/test-import-api.php

