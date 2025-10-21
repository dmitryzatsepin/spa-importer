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
}
