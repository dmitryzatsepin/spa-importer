<?php

namespace App\Services\Bitrix24;

use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class Bitrix24APIService
{
    protected string $domain;
    protected string $accessToken;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct(
        string $domain,
        string $accessToken,
        int $timeout = 30,
        int $connectTimeout = 5
    ) {
        $this->domain = rtrim($domain, '/');
        $this->accessToken = $accessToken;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Выполнить одиночный API запрос к Битрикс24
     *
     * @param string $method Метод API (например, 'crm.deal.list')
     * @param array $params Параметры запроса
     * @return array Результат запроса
     * @throws Bitrix24APIException
     */
    public function call(string $method, array $params = []): array
    {
        $url = $this->buildUrl($method);

        $params['auth'] = $this->accessToken;

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->asJson()
                ->post($url, $params);

            $data = $response->json();

            if (!$response->successful()) {
                throw new Bitrix24APIException(
                    sprintf('HTTP ошибка: %d - %s', $response->status(), $response->body()),
                    [
                        'method' => $method,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]
                );
            }

            // Проверка на ошибки API Битрикс24
            if (isset($data['error'])) {
                throw new Bitrix24APIException(
                    sprintf(
                        'Ошибка API Битрикс24: [%s] %s',
                        $data['error'],
                        $data['error_description'] ?? 'Нет описания'
                    ),
                    [
                        'method' => $method,
                        'error' => $data['error'],
                        'error_description' => $data['error_description'] ?? null,
                        'params' => $params
                    ]
                );
            }

            return [
                'result' => $data['result'] ?? null,
                'total' => $data['total'] ?? 0,
                'time' => $data['time'] ?? null,
                'next' => $data['next'] ?? null,
            ];

        } catch (RequestException $e) {
            Log::error('Bitrix24 API Request Exception', [
                'method' => $method,
                'message' => $e->getMessage(),
                'params' => $params
            ]);

            throw new Bitrix24APIException(
                sprintf('Ошибка выполнения запроса к Битрикс24: %s', $e->getMessage()),
                [
                    'method' => $method,
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ],
                0,
                $e
            );
        } catch (\Exception $e) {
            if ($e instanceof Bitrix24APIException) {
                throw $e;
            }

            Log::error('Bitrix24 API Unexpected Exception', [
                'method' => $method,
                'message' => $e->getMessage(),
                'params' => $params
            ]);

            throw new Bitrix24APIException(
                sprintf('Неожиданная ошибка при запросе к Битрикс24: %s', $e->getMessage()),
                [
                    'method' => $method,
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ],
                0,
                $e
            );
        }
    }

    /**
     * Выполнить пакетный запрос к Битрикс24
     *
     * @param Bitrix24BatchRequest $batchRequest
     * @return array Результаты выполнения команд
     * @throws Bitrix24APIException
     */
    public function callBatch(Bitrix24BatchRequest $batchRequest): array
    {
        if (!$batchRequest->hasCommands()) {
            throw new Bitrix24APIException('Пакетный запрос не содержит команд');
        }

        $url = $this->buildUrl('batch');

        $params = [
            'auth' => $this->accessToken,
            'cmd' => $batchRequest->getCommands(),
        ];

        if ($batchRequest->getHalt()) {
            $params['halt'] = 1;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->asJson()
                ->post($url, $params);

            $data = $response->json();

            if (!$response->successful()) {
                throw new Bitrix24APIException(
                    sprintf('HTTP ошибка при пакетном запросе: %d - %s', $response->status(), $response->body()),
                    [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'commands_count' => $batchRequest->count()
                    ]
                );
            }

            // Проверка на общую ошибку пакетного запроса
            if (isset($data['error'])) {
                throw new Bitrix24APIException(
                    sprintf(
                        'Ошибка пакетного API запроса: [%s] %s',
                        $data['error'],
                        $data['error_description'] ?? 'Нет описания'
                    ),
                    [
                        'error' => $data['error'],
                        'error_description' => $data['error_description'] ?? null,
                        'commands_count' => $batchRequest->count()
                    ]
                );
            }

            // Формируем результат
            $results = [];
            $commandKeys = array_keys($batchRequest->getCommands());

            foreach ($commandKeys as $key) {
                $results[$key] = [
                    'result' => $data['result']['result'][$key] ?? null,
                    'error' => $data['result']['result_error'][$key] ?? null,
                    'time' => $data['result']['result_time'][$key] ?? null,
                    'total' => $data['result']['result_total'][$key] ?? 0,
                ];
            }

            return [
                'results' => $results,
                'time' => $data['time'] ?? null,
                'total' => count($results),
            ];

        } catch (RequestException $e) {
            Log::error('Bitrix24 Batch Request Exception', [
                'message' => $e->getMessage(),
                'commands_count' => $batchRequest->count()
            ]);

            throw new Bitrix24APIException(
                sprintf('Ошибка выполнения пакетного запроса: %s', $e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'commands_count' => $batchRequest->count()
                ],
                0,
                $e
            );
        } catch (\Exception $e) {
            if ($e instanceof Bitrix24APIException) {
                throw $e;
            }

            Log::error('Bitrix24 Batch Unexpected Exception', [
                'message' => $e->getMessage(),
                'commands_count' => $batchRequest->count()
            ]);

            throw new Bitrix24APIException(
                sprintf('Неожиданная ошибка при пакетном запросе: %s', $e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'commands_count' => $batchRequest->count()
                ],
                0,
                $e
            );
        }
    }

    /**
     * Построить URL для API метода
     */
    protected function buildUrl(string $method): string
    {
        return sprintf('https://%s/rest/%s.json', $this->domain, $method);
    }

    /**
     * Получить домен портала
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Установить новый токен доступа
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }
}

