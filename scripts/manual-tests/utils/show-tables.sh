#!/bin/bash
# Обёртка для запуска show-tables.php
# Использование: ./show-tables.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/utils/show-tables.php

