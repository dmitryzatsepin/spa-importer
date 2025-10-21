<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockImportController extends Controller
{
    public function getSmartProcesses(Request $request): JsonResponse
    {
        $request->validate([
            'portal_id' => ['required', 'integer'],
        ]);

        // Мок данные для демонстрации
        $smartProcesses = [
            [
                'id' => 128,
                'title' => 'Проекты',
                'code' => 'projects'
            ],
            [
                'id' => 130,
                'title' => 'Задачи',
                'code' => 'tasks'
            ],
            [
                'id' => 132,
                'title' => 'Лиды',
                'code' => 'leads'
            ],
            [
                'id' => 134,
                'title' => 'Сделки',
                'code' => 'deals'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $smartProcesses,
        ]);
    }

    public function getSmartProcessFields(Request $request, int $entityTypeId): JsonResponse
    {
        $request->validate([
            'portal_id' => ['required', 'integer'],
        ]);

        // Мок поля для демонстрации
        $fields = [
            [
                'code' => 'TITLE',
                'title' => 'Название',
                'type' => 'string',
                'isRequired' => true,
                'isReadOnly' => false
            ],
            [
                'code' => 'ASSIGNED_BY_ID',
                'title' => 'Ответственный',
                'type' => 'user',
                'isRequired' => false,
                'isReadOnly' => false
            ],
            [
                'code' => 'STAGE_ID',
                'title' => 'Стадия',
                'type' => 'enum',
                'isRequired' => false,
                'isReadOnly' => false
            ],
            [
                'code' => 'OPPORTUNITY',
                'title' => 'Сумма',
                'type' => 'money',
                'isRequired' => false,
                'isReadOnly' => false
            ],
            [
                'code' => 'COMMENTS',
                'title' => 'Комментарии',
                'type' => 'text',
                'isRequired' => false,
                'isReadOnly' => false
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $fields,
        ]);
    }

    public function startImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
            'portal_id' => ['required', 'integer'],
            'entity_type_id' => ['required', 'integer'],
            'field_mappings' => ['required', 'array'],
        ]);

        // Мок создания задачи импорта
        $jobId = rand(100, 999);

        return response()->json([
            'success' => true,
            'message' => 'Задача импорта создана (мок)',
            'data' => [
                'job_id' => $jobId,
            ],
        ], 202);
    }

    public function getImportStatus(int $jobId): JsonResponse
    {
        // Мок статуса импорта
        $statuses = ['pending', 'processing', 'completed', 'failed'];
        $status = $statuses[array_rand($statuses)];

        $totalRows = rand(50, 200);
        $processedRows = $status === 'completed' ? $totalRows : rand(0, $totalRows);
        $progressPercentage = $totalRows > 0 ? ($processedRows / $totalRows) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => $status,
                'original_filename' => 'test_data.csv',
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'progress_percentage' => round($progressPercentage, 1),
                'error_details' => $status === 'failed' ? 'Тестовая ошибка импорта' : null,
                'created_at' => now()->subMinutes(rand(1, 30))->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'portal_id' => ['required', 'integer'],
        ]);

        // Мок истории импортов
        $mockJobs = [];
        for ($i = 0; $i < 15; $i++) {
            $statuses = ['pending', 'processing', 'completed', 'failed'];
            $status = $statuses[array_rand($statuses)];
            $totalRows = rand(50, 500);
            $processedRows = in_array($status, ['completed', 'failed']) ? $totalRows : rand(0, $totalRows);
            $hasErrors = $status === 'failed' || ($status === 'completed' && rand(0, 1));

            $mockJobs[] = [
                'job_id' => 100 + $i,
                'status' => $status,
                'original_filename' => sprintf('import_data_%d.csv', $i + 1),
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'progress_percentage' => round(($processedRows / $totalRows) * 100, 2),
                'has_errors' => $hasErrors,
                'error_count' => $hasErrors ? rand(1, 10) : 0,
                'created_at' => now()->subDays(rand(1, 30))->toISOString(),
                'updated_at' => now()->subDays(rand(0, 15))->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $mockJobs,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => count($mockJobs),
            ],
        ]);
    }

    public function downloadErrorLog(int $jobId)
    {
        // Мок CSV с ошибками
        $csvContent = "\xEF\xBB\xBF"; // BOM для UTF-8
        $csvContent .= "Номер строки;Ошибка;Исходные данные\n";
        $csvContent .= "5;Не заполнено обязательное поле 'TITLE';{\"TITLE\":\"\",\"ASSIGNED_BY_ID\":\"123\"}\n";
        $csvContent .= "12;Некорректный формат даты;{\"TITLE\":\"Тест\",\"DATE\":\"invalid-date\"}\n";
        $csvContent .= "18;Пользователь с ID 999 не найден;{\"TITLE\":\"Элемент\",\"ASSIGNED_BY_ID\":\"999\"}\n";

        $filename = sprintf('error_log_%s_%s.csv', $jobId, date('Y-m-d_H-i-s'));

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Content-Length' => strlen($csvContent),
        ]);
    }
}
