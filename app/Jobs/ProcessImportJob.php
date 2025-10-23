<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Portal;
use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    protected int $importJobId;
    protected int $progressUpdateInterval = 100;
    protected int $batchSize = 10;

    public function __construct(int $importJobId)
    {
        $this->importJobId = $importJobId;
    }

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            Log::error('Import job not found', ['job_id' => $this->importJobId]);
            return;
        }

        try {
            Log::info('Начало обработки импорта', [
                'job_id' => $importJob->id,
                'filename' => $importJob->original_filename,
            ]);

            $importJob->markAsProcessing();

            $portal = $importJob->portal;
            if (!$portal) {
                throw new \RuntimeException('Портал не найден для задачи импорта');
            }

            $apiService = new Bitrix24APIService(
                $portal->domain,
                $portal->access_token,
                60,
                10,
                $portal
            );

            $this->processFile($importJob, $apiService);

            $importJob->markAsCompleted();

            Log::info('Импорт успешно завершен', [
                'job_id' => $importJob->id,
                'processed_rows' => $importJob->processed_rows,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка при обработке импорта', [
                'job_id' => $importJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $importJob->markAsFailed([
                [
                    'error' => $e->getMessage(),
                    'data' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ],
            ]);
        }
    }

    protected function processFile(ImportJob $importJob, Bitrix24APIService $apiService): void
    {
        $filePath = Storage::path($importJob->stored_filepath);

        if (!file_exists($filePath)) {
            throw new \RuntimeException('Файл импорта не найден: ' . $filePath);
        }

        $extension = strtolower(pathinfo($importJob->original_filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'])) {
            $this->processExcelFile($filePath, $importJob, $apiService);
        } elseif ($extension === 'csv') {
            $this->processCsvFile($filePath, $importJob, $apiService);
        } else {
            throw new \RuntimeException('Неподдерживаемый формат файла: ' . $extension);
        }
    }

    protected function processExcelFile(string $filePath, ImportJob $importJob, Bitrix24APIService $apiService): void
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Получаем итератор для строк
        $rowIterator = $worksheet->getRowIterator();

        // Читаем первую строку (заголовки)
        $rowIterator->rewind();
        if (!$rowIterator->valid()) {
            throw new \RuntimeException('Файл пуст');
        }

        $headerRow = $rowIterator->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $headers = [];
        foreach ($cellIterator as $cell) {
            $headers[] = $cell->getValue();
        }

        // Подсчитываем total_rows (без загрузки всех данных в память)
        $totalRows = $worksheet->getHighestRow() - 1; // -1 для заголовка
        $importJob->total_rows = $totalRows;
        $importJob->save();

        // Переходим к следующей строке (пропускаем заголовки)
        $rowIterator->next();

        // Обрабатываем строки через итератор
        $this->processRowsIterator($rowIterator, $headers, $importJob, $apiService);

        // Освобождаем память
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    protected function processCsvFile(string $filePath, ImportJob $importJob, Bitrix24APIService $apiService): void
    {
        $delimiter = $this->detectCsvDelimiter($filePath);
        $encoding = $this->detectEncoding($filePath);

        // Создаем SplFileObject для потокового чтения
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        // Читаем заголовки
        $file->rewind();
        $headers = $file->current();

        if (!$headers || empty($headers)) {
            throw new \RuntimeException('Файл пуст');
        }

        // Конвертируем заголовки из исходной кодировки в UTF-8
        if ($encoding !== 'UTF-8') {
            $headers = array_map(function ($header) use ($encoding) {
                return mb_convert_encoding($header, 'UTF-8', $encoding);
            }, $headers);
        }

        // Подсчитываем количество строк (без загрузки в память)
        $totalRows = $this->countCsvRows($filePath) - 1; // -1 для заголовка
        $importJob->total_rows = $totalRows;
        $importJob->save();

        // Переходим к первой строке данных
        $file->next();

        // Обрабатываем через итератор
        $this->processCsvIterator($file, $headers, $encoding, $importJob, $apiService);
    }

    protected function processRowsIterator($rowIterator, array $headers, ImportJob $importJob, Bitrix24APIService $apiService): void
    {
        $entityTypeId = $importJob->settings['entity_type_id'] ?? null;
        if (!$entityTypeId) {
            throw new \RuntimeException('entity_type_id не указан в настройках');
        }

        $this->batchSize = $importJob->settings['batch_size'] ?? 10;
        $duplicateHandling = $importJob->settings['duplicate_handling'] ?? 'skip';

        $batchRequest = new Bitrix24BatchRequest();
        $processedCount = 0;
        $errors = [];
        $rowIndex = 0;

        foreach ($rowIterator as $row) {
            try {
                // Преобразуем объекты Cell в массив значений
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                $fields = $this->transformRowToFields($rowData, $headers, $importJob->field_mappings);

                if (empty($fields)) {
                    Log::warning('Пропущена пустая строка', ['row' => $rowIndex + 2]);
                    $rowIndex++;
                    continue;
                }

                if ($duplicateHandling !== 'skip' || !$this->isDuplicate($fields, $entityTypeId, $apiService, $importJob)) {
                    $batchRequest->addCommand(
                        "row_{$rowIndex}",
                        'crm.item.add',
                        [
                            'entityTypeId' => $entityTypeId,
                            'fields' => $fields,
                        ]
                    );
                }

                $processedCount++;

                if ($batchRequest->count() >= $this->batchSize) {
                    $batchErrors = $this->executeBatch($batchRequest, $apiService);
                    $errors = array_merge($errors, $batchErrors);
                    $batchRequest->clear();
                }

                if ($processedCount % $this->progressUpdateInterval === 0) {
                    $importJob->updateProgress($processedCount, !empty($errors) ? $errors : null);
                }

            } catch (\Exception $e) {
                Log::error('Ошибка обработки строки', [
                    'job_id' => $importJob->id,
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                ];
            }

            $rowIndex++;
        }

        if ($batchRequest->hasCommands()) {
            $batchErrors = $this->executeBatch($batchRequest, $apiService);
            $errors = array_merge($errors, $batchErrors);
        }

        $importJob->updateProgress($processedCount, !empty($errors) ? $errors : null);
    }

    protected function processCsvIterator(\SplFileObject $file, array $headers, string $encoding, ImportJob $importJob, Bitrix24APIService $apiService): void
    {
        $entityTypeId = $importJob->settings['entity_type_id'] ?? null;
        if (!$entityTypeId) {
            throw new \RuntimeException('entity_type_id не указан в настройках');
        }

        $this->batchSize = $importJob->settings['batch_size'] ?? 10;
        $duplicateHandling = $importJob->settings['duplicate_handling'] ?? 'skip';

        $batchRequest = new Bitrix24BatchRequest();
        $processedCount = 0;
        $errors = [];
        $rowIndex = 0;

        while (!$file->eof()) {
            $row = $file->current();

            // Пропускаем пустые строки или некорректные данные
            if (!$row || (count($row) === 1 && ($row[0] === null || $row[0] === ''))) {
                $file->next();
                continue;
            }

            try {
                // Конвертируем из исходной кодировки в UTF-8
                if ($encoding !== 'UTF-8') {
                    $row = array_map(function ($value) use ($encoding) {
                        return $value !== null ? mb_convert_encoding($value, 'UTF-8', $encoding) : null;
                    }, $row);
                }

                $fields = $this->transformRowToFields($row, $headers, $importJob->field_mappings);

                if (empty($fields)) {
                    Log::warning('Пропущена пустая строка', ['row' => $rowIndex + 2]);
                    $file->next();
                    $rowIndex++;
                    continue;
                }

                if ($duplicateHandling !== 'skip' || !$this->isDuplicate($fields, $entityTypeId, $apiService, $importJob)) {
                    $batchRequest->addCommand(
                        "row_{$rowIndex}",
                        'crm.item.add',
                        [
                            'entityTypeId' => $entityTypeId,
                            'fields' => $fields,
                        ]
                    );
                }

                $processedCount++;

                if ($batchRequest->count() >= $this->batchSize) {
                    $batchErrors = $this->executeBatch($batchRequest, $apiService);
                    $errors = array_merge($errors, $batchErrors);
                    $batchRequest->clear();
                }

                if ($processedCount % $this->progressUpdateInterval === 0) {
                    $importJob->updateProgress($processedCount, !empty($errors) ? $errors : null);
                }

            } catch (\Exception $e) {
                Log::error('Ошибка обработки строки', [
                    'job_id' => $importJob->id,
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                ];
            }

            $file->next();
            $rowIndex++;
        }

        if ($batchRequest->hasCommands()) {
            $batchErrors = $this->executeBatch($batchRequest, $apiService);
            $errors = array_merge($errors, $batchErrors);
        }

        $importJob->updateProgress($processedCount, !empty($errors) ? $errors : null);
    }

    protected function countCsvRows(string $filePath): int
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $count = 0;
        while (!$file->eof()) {
            $row = $file->current();
            // Считаем только непустые строки
            if ($row && !(count($row) === 1 && ($row[0] === null || $row[0] === ''))) {
                $count++;
            }
            $file->next();
        }

        return $count;
    }

    protected function transformRowToFields(array $row, array $headers, array $fieldMappings): array
    {
        $fields = [];

        foreach ($fieldMappings as $mapping) {
            // Новый формат: source/target, fallback: source_column/target_field
            $sourceColumn = $mapping['source'] ?? $mapping['source_column'] ?? null;
            $targetField = $mapping['target'] ?? $mapping['target_field'] ?? null;
            $transform = $mapping['transform'] ?? null;

            if (!$sourceColumn || !$targetField) {
                continue;
            }

            $columnIndex = array_search($sourceColumn, $headers);
            if ($columnIndex === false) {
                continue;
            }

            $value = $row[$columnIndex] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $value = $this->applyTransform($value, $transform, $mapping);

            if ($value !== null) {
                $fields[$targetField] = $value;
            }
        }

        return $fields;
    }

    protected function applyTransform($value, ?string $transform, array $mapping)
    {
        if (!$transform) {
            return $value;
        }

        try {
            switch ($transform) {
                case 'date':
                    return $this->transformDate($value, $mapping);

                case 'datetime':
                    return $this->transformDateTime($value, $mapping);

                case 'user':
                    return $this->transformUser($value, $mapping);

                case 'boolean':
                    return $this->transformBoolean($value);

                case 'number':
                    return $this->transformNumber($value);

                case 'crm_entity':
                    return $this->transformCrmEntity($value, $mapping);

                default:
                    return $value;
            }
        } catch (\Exception $e) {
            Log::warning('Ошибка преобразования значения', [
                'value' => $value,
                'transform' => $transform,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function transformDate($value, array $mapping): ?string
    {
        if (is_numeric($value)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $date->format('Y-m-d');
        }

        $format = $mapping['date_format'] ?? 'd.m.Y';

        try {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Попытаемся с автоопределением
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    protected function transformDateTime($value, array $mapping): ?string
    {
        if (is_numeric($value)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $date->format('c');
        }

        $format = $mapping['datetime_format'] ?? 'd.m.Y H:i:s';

        try {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format('c');
            }
        } catch (\Exception $e) {
            // Попытаемся с автоопределением
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('c', $timestamp);
        }

        return null;
    }

    protected function transformUser($value, array $mapping): ?int
    {
        // Если значение уже является ID
        if (is_numeric($value) && intval($value) > 0) {
            return intval($value);
        }

        // В будущем здесь можно добавить поиск пользователя по email или имени
        Log::warning('Не удалось преобразовать пользователя', ['value' => $value]);

        return null;
    }

    protected function transformBoolean($value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'Y' : 'N';
        }

        $value = strtolower(trim($value));

        if (in_array($value, ['1', 'true', 'yes', 'да', 'y', '+'])) {
            return 'Y';
        }

        if (in_array($value, ['0', 'false', 'no', 'нет', 'n', '-'])) {
            return 'N';
        }

        return null;
    }

    protected function transformNumber($value): ?float
    {
        $value = str_replace([' ', ','], ['', '.'], trim($value));

        if (is_numeric($value)) {
            return floatval($value);
        }

        return null;
    }

    protected function transformCrmEntity($value, array $mapping): ?string
    {
        // Формат: ENTITY_TYPE_ID:ID или просто ID
        $entityType = $mapping['entity_type'] ?? null;

        if (!$entityType) {
            return null;
        }

        if (is_numeric($value) && intval($value) > 0) {
            return "{$entityType}_{$value}";
        }

        return null;
    }

    protected function isDuplicate(array $fields, int $entityTypeId, Bitrix24APIService $apiService, ImportJob $importJob): bool
    {
        // Новый формат: duplicate_field, fallback: duplicate_check_field
        $duplicateField = $importJob->settings['duplicate_field'] ?? $importJob->settings['duplicate_check_field'] ?? null;

        if (!$duplicateField || !isset($fields[$duplicateField])) {
            return false;
        }

        try {
            $result = $apiService->call('crm.item.list', [
                'entityTypeId' => $entityTypeId,
                'filter' => [
                    $duplicateField => $fields[$duplicateField],
                ],
                'select' => ['ID'],
            ]);

            return !empty($result['result']);

        } catch (\Exception $e) {
            Log::warning('Ошибка проверки дубликата', [
                'field' => $duplicateField,
                'value' => $fields[$duplicateField],
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function executeBatch(Bitrix24BatchRequest $batchRequest, Bitrix24APIService $apiService): array
    {
        $errors = [];

        try {
            $result = $apiService->callBatch($batchRequest);

            foreach ($result['results'] as $commandKey => $commandResult) {
                if ($commandResult['error']) {
                    $errors[] = [
                        'error' => $commandResult['error'],
                        'data' => [
                            'command' => $commandKey,
                        ],
                    ];

                    Log::warning('Ошибка выполнения команды в батче', [
                        'command' => $commandKey,
                        'error' => $commandResult['error'],
                    ]);
                }
            }

        } catch (Bitrix24APIException $e) {
            Log::error('Ошибка выполнения батча', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            $errors[] = [
                'error' => $e->getMessage(),
                'data' => [
                    'batch' => 'execution_failed',
                    'context' => $e->getContext(),
                ],
            ];
        }

        return $errors;
    }

    protected function detectCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ',';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($firstLine, $delimiter);
        }

        arsort($counts);
        return array_key_first($counts);
    }

    protected function detectEncoding(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return 'UTF-8';
        }

        $sample = fread($handle, 8192);
        fclose($handle);

        $encoding = mb_detect_encoding($sample, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);

        return $encoding ?: 'UTF-8';
    }
}
