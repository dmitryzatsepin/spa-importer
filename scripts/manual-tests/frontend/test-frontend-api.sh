#!/bin/bash
# Обёртка для запуска test-frontend-api.php
# Использование: ./test-frontend-api.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/frontend/test-frontend-api.php

