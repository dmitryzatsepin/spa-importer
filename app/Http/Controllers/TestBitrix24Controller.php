<?php

namespace App\Http\Controllers;

use App\Services\Bitrix24\Bitrix24APIService;
use App\Services\Bitrix24\Bitrix24BatchRequest;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestBitrix24Controller extends Controller
{
    /**
     * Тест одиночного запроса к API Битрикс24
     * Пример: GET /test-bitrix24/single?domain=ВАШЕ_ДОМЕН&token=ВАШ_ТОКЕН
     */
    public function testSingleCall(Request $request): JsonResponse
    {
        $domain = $request->input('domain');
        $token = $request->input('token');

        if (!$domain || !$token) {
            return response()->json([
                'error' => 'Требуются параметры domain и token'
            ], 400);
        }

        try {
            $service = new Bitrix24APIService($domain, $token);
            $result = $service->call('app.info');

            return response()->json([
                'success' => true,
                'message' => 'Одиночный запрос выполнен успешно',
                'data' => $result
            ]);
        } catch (Bitrix24APIException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ], 500);
        }
    }

    /**
     * Тест пакетного запроса к API Битрикс24
     * Пример: GET /test-bitrix24/batch?domain=ВАШЕ_ДОМЕН&token=ВАШ_ТОКЕН
     */
    public function testBatchCall(Request $request): JsonResponse
    {
        $domain = $request->input('domain');
        $token = $request->input('token');

        if (!$domain || !$token) {
            return response()->json([
                'error' => 'Требуются параметры domain и token'
            ], 400);
        }

        try {
            $service = new Bitrix24APIService($domain, $token);

            $batchRequest = new Bitrix24BatchRequest();
            $batchRequest
                ->addCommand('app_info', 'app.info')
                ->addCommand('current_user', 'user.current')
                ->addCommand('deals_list', 'crm.deal.list', ['filter' => ['ID' => 1]]);

            $result = $service->callBatch($batchRequest);

            return response()->json([
                'success' => true,
                'message' => 'Пакетный запрос выполнен успешно',
                'commands_count' => $batchRequest->count(),
                'data' => $result
            ]);
        } catch (Bitrix24APIException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ], 500);
        }
    }

    /**
     * Тест обработки ошибок API
     * Пример: GET /test-bitrix24/error?domain=ВАШЕ_ДОМЕН&token=ВАШ_ТОКЕН
     */
    public function testErrorHandling(Request $request): JsonResponse
    {
        $domain = $request->input('domain');
        $token = $request->input('token');

        if (!$domain || !$token) {
            return response()->json([
                'error' => 'Требуются параметры domain и token'
            ], 400);
        }

        try {
            $service = new Bitrix24APIService($domain, $token);

            // Вызываем несуществующий метод
            $result = $service->call('non.existent.method');

            // Этот код не должен выполниться
            return response()->json([
                'success' => true,
                'message' => 'Неожиданно: ошибка не была перехвачена',
                'data' => $result
            ]);
        } catch (Bitrix24APIException $e) {
            // Ожидаемое поведение - ошибка корректно обработана
            return response()->json([
                'success' => true,
                'message' => 'Ошибка корректно перехвачена и обработана',
                'error_message' => $e->getMessage(),
                'error_context' => $e->getContext()
            ]);
        }
    }

    /**
     * Тест с невалидным токеном
     * Пример: GET /test-bitrix24/invalid-token?domain=ВАШЕ_ДОМЕН
     */
    public function testInvalidToken(Request $request): JsonResponse
    {
        $domain = $request->input('domain');

        if (!$domain) {
            return response()->json([
                'error' => 'Требуется параметр domain'
            ], 400);
        }

        try {
            $service = new Bitrix24APIService($domain, 'invalid_token_12345');
            $result = $service->call('app.info');

            return response()->json([
                'success' => true,
                'message' => 'Неожиданно: запрос с невалидным токеном прошел',
                'data' => $result
            ]);
        } catch (Bitrix24APIException $e) {
            return response()->json([
                'success' => true,
                'message' => 'Ошибка с невалидным токеном корректно перехвачена',
                'error_message' => $e->getMessage(),
                'error_context' => $e->getContext()
            ]);
        }
    }

    /**
     * Главная страница с инструкциями
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Тестовые маршруты для Bitrix24 API Service',
            'endpoints' => [
                [
                    'url' => '/test-bitrix24/single?domain=YOUR_DOMAIN&token=YOUR_TOKEN',
                    'description' => 'Тест одиночного запроса (app.info)',
                    'method' => 'GET'
                ],
                [
                    'url' => '/test-bitrix24/batch?domain=YOUR_DOMAIN&token=YOUR_TOKEN',
                    'description' => 'Тест пакетного запроса (app.info + user.current + crm.deal.list)',
                    'method' => 'GET'
                ],
                [
                    'url' => '/test-bitrix24/error?domain=YOUR_DOMAIN&token=YOUR_TOKEN',
                    'description' => 'Тест обработки ошибок (вызов несуществующего метода)',
                    'method' => 'GET'
                ],
                [
                    'url' => '/test-bitrix24/invalid-token?domain=YOUR_DOMAIN',
                    'description' => 'Тест с невалидным токеном',
                    'method' => 'GET'
                ],
            ],
            'note' => 'Замените YOUR_DOMAIN и YOUR_TOKEN на ваши реальные значения'
        ]);
    }
}

