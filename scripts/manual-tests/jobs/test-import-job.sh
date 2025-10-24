#!/bin/bash
# Обёртка для запуска test-import-job.php
# Использование: ./test-import-job.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../.." || exit 1

php scripts/manual-tests/jobs/test-import-job.php


