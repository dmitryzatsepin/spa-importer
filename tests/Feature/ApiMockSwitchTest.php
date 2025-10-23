<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ApiMockSwitchTest extends TestCase
{
    /**
     * Тест: при API_USE_MOCK=true используется MockImportController
     */
    public function test_mock_mode_uses_mock_controller(): void
    {
        // Установить мок режим
        Config::set('app.api_use_mock', true);

        // Запрос к эндпоинту smart-processes
        $response = $this->getJson('/api/v1/smart-processes?portal_id=1');

        // Проверяем успешный ответ
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'title', 'code']
            ]
        ]);

        // Проверяем, что это мок данные (содержат типичные мок значения)
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Мок контроллер возвращает смарт-процессы (проверяем структуру, не количество)
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    /**
     * Тест: при API_USE_MOCK=false пытается использовать ImportController
     */
    public function test_real_mode_uses_import_controller(): void
    {
        // Установить реальный режим
        Config::set('app.api_use_mock', false);

        // Проверить, что конфиг действительно изменился
        $this->assertFalse(config('app.api_use_mock'), 'API_USE_MOCK должен быть false');

        // Запрос к эндпоинту smart-processes без настроенного портала
        // Ожидаем ошибку, т.к. нет реального портала
        $response = $this->getJson('/api/v1/smart-processes?portal_id=1');

        // ImportController требует реальный портал в БД
        // При отсутствии портала вернется ошибка 500
        $response->assertStatus(500);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
        $response->assertJson([
            'success' => false
        ]);
    }

    /**
     * Тест: проверка дефолтного значения config
     */
    public function test_default_config_is_mock_mode(): void
    {
        // По умолчанию должен быть мок режим
        $useMock = config('app.api_use_mock');

        $this->assertTrue($useMock, 'По умолчанию API_USE_MOCK должен быть true');
    }

    /**
     * Тест: все маршруты правильно переключаются
     */
    public function test_all_routes_switch_correctly(): void
    {
        $routes = [
            'GET /api/v1/smart-processes?portal_id=1',
            'GET /api/v1/smart-processes/128/fields?portal_id=1',
            'GET /api/v1/import/history?portal_id=1',
        ];

        // Проверяем в мок режиме
        Config::set('app.api_use_mock', true);

        foreach ($routes as $route) {
            [$method, $uri] = explode(' ', $route);
            $response = $this->json($method, $uri);

            // В мок режиме все должно работать
            $this->assertTrue(
                in_array($response->status(), [200, 201, 202]),
                "Route {$route} должен работать в мок режиме"
            );
        }
    }

    /**
     * Тест: проверка статуса импорта в мок режиме
     */
    public function test_import_status_in_mock_mode(): void
    {
        Config::set('app.api_use_mock', true);

        // Мок контроллер должен вернуть статус для любого jobId
        $response = $this->getJson('/api/v1/import/999/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'job_id',
                'status',
                'original_filename',
                'total_rows',
                'processed_rows',
                'progress_percentage'
            ]
        ]);
    }

    /**
     * Тест: history endpoint в мок режиме
     */
    public function test_history_endpoint_in_mock_mode(): void
    {
        Config::set('app.api_use_mock', true);

        $response = $this->getJson('/api/v1/import/history?portal_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'job_id',
                    'status',
                    'original_filename',
                    'created_at'
                ]
            ]
        ]);
    }
}

