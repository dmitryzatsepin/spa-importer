#!/bin/bash
# Обёртка для запуска test-api-switch.php
# Использование: ./test-api-switch.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/api/test-api-switch.php

