<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartImportRequest;
use App\Models\ImportJob;
use App\Models\Portal;
use App\Services\Bitrix24\Bitrix24APIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function getSmartProcesses(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'portal_id' => ['required', 'integer', 'exists:portals,id'],
            ]);

            $portal = Portal::findOrFail($request->portal_id);

            $apiService = new Bitrix24APIService(
                $portal->domain,
                $portal->access_token,
                30,
                5,
                $portal
            );

            $response = $apiService->call('crm.type.list', [
                'filter' => ['isExternal' => 'N']
            ]);

            $smartProcesses = collect($response['result'] ?? [])
                ->map(fn($type) => [
                    'id' => $type['entityTypeId'],
                    'title' => $type['title'],
                    'code' => $type['code'] ?? null,
                ]);

            return response()->json([
                'success' => true,
                'data' => $smartProcesses,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка получения смарт-процессов', [
                'message' => $e->getMessage(),
                'portal_id' => $request->portal_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить список смарт-процессов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSmartProcessFields(Request $request, int $entityTypeId): JsonResponse
    {
        try {
            $request->validate([
                'portal_id' => ['required', 'integer', 'exists:portals,id'],
            ]);

            $portal = Portal::findOrFail($request->portal_id);

            $apiService = new Bitrix24APIService(
                $portal->domain,
                $portal->access_token,
                30,
                5,
                $portal
            );

            $response = $apiService->call('crm.item.fields', [
                'entityTypeId' => $entityTypeId,
            ]);

            $fields = collect($response['result']['fields'] ?? [])
                ->map(fn($field, $code) => [
                    'code' => $code,
                    'title' => $field['title'] ?? $code,
                    'type' => $field['type'] ?? 'string',
                    'isRequired' => $field['isRequired'] ?? false,
                    'isReadOnly' => $field['isReadOnly'] ?? false,
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => $fields,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка получения полей смарт-процесса', [
                'message' => $e->getMessage(),
                'entity_type_id' => $entityTypeId,
                'portal_id' => $request->portal_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить поля смарт-процесса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startImport(StartImportRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();

            $filename = sprintf(
                '%s_%s_%s',
                time(),
                uniqid(),
                $originalFilename
            );

            $storedPath = $file->storeAs('imports', $filename, 'local');

            if (!$storedPath) {
                throw new \RuntimeException('Не удалось сохранить файл');
            }

            $importJob = ImportJob::create([
                'portal_id' => $request->portal_id,
                'status' => 'pending',
                'original_filename' => $originalFilename,
                'stored_filepath' => $storedPath,
                'field_mappings' => $request->field_mappings,
                'settings' => array_merge([
                    'entity_type_id' => $request->entity_type_id,
                    'duplicate_handling' => 'skip',
                    'batch_size' => 10,
                ], $request->settings ?? []),
                'total_rows' => 0,
                'processed_rows' => 0,
            ]);

            // TODO: Поставить в очередь ProcessImportJob
            // dispatch(new ProcessImportJob($importJob->id));

            Log::info('Создана задача импорта', [
                'job_id' => $importJob->id,
                'filename' => $originalFilename,
                'portal_id' => $request->portal_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Задача импорта создана',
                'data' => [
                    'job_id' => $importJob->id,
                ],
            ], 202);

        } catch (\Exception $e) {
            Log::error('Ошибка создания задачи импорта', [
                'message' => $e->getMessage(),
                'portal_id' => $request->portal_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось создать задачу импорта',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getImportStatus(int $jobId): JsonResponse
    {
        try {
            $importJob = ImportJob::findOrFail($jobId);

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $importJob->id,
                    'status' => $importJob->status,
                    'original_filename' => $importJob->original_filename,
                    'total_rows' => $importJob->total_rows,
                    'processed_rows' => $importJob->processed_rows,
                    'progress_percentage' => $importJob->getProgressPercentage(),
                    'error_details' => $importJob->error_details,
                    'created_at' => $importJob->created_at,
                    'updated_at' => $importJob->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка получения статуса импорта', [
                'message' => $e->getMessage(),
                'job_id' => $jobId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить статус задачи импорта',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}

