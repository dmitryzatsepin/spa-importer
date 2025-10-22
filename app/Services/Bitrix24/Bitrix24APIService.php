<?php

namespace App\Services\Bitrix24;

use App\Models\Portal;
use App\Services\Bitrix24\Exceptions\Bitrix24APIException;
use App\Services\Bitrix24\Exceptions\TokenRefreshException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class Bitrix24APIService
{
    protected string $domain;
    protected string $accessToken;
    protected int $timeout;
    protected int $connectTimeout;
    protected ?Portal $portal;

    public function __construct(
        string $domain,
        string $accessToken,
        int $timeout = 30,
        int $connectTimeout = 5,
        ?Portal $portal = null
    ) {
        $this->domain = rtrim($domain, '/');
        $this->accessToken = $accessToken;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->portal = $portal;
    }

    /**
     * Выполнить одиночный API запрос к Битрикс24
     *
     * @param string $method Метод API (например, 'crm.deal.list')
     * @param array $params Параметры запроса
     * @return array Результат запроса
     * @throws Bitrix24APIException
     * @throws TokenRefreshException
     */
    public function call(string $method, array $params = []): array
    {
        $this->ensureValidToken();

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
     * Автоматически разбивает запросы >50 команд на несколько батчей
     *
     * @param Bitrix24BatchRequest $batchRequest
     * @param int $maxRetries Максимальное количество повторных попыток при временных ошибках
     * @return array Результаты выполнения команд
     * @throws Bitrix24APIException
     * @throws TokenRefreshException
     */
    public function callBatch(Bitrix24BatchRequest $batchRequest, int $maxRetries = 0): array
    {
        $this->ensureValidToken();

        if (!$batchRequest->hasCommands()) {
            throw new Bitrix24APIException('Пакетный запрос не содержит команд');
        }

        // Если команд больше 50, разбиваем на несколько батчей
        if ($batchRequest->needsSplitting()) {
            return $this->callBatchMultiple($batchRequest, $maxRetries);
        }

        return $this->executeSingleBatch($batchRequest, $maxRetries);
    }

    /**
     * Выполнить несколько пакетных запросов (для >50 команд)
     * 
     * @param Bitrix24BatchRequest $batchRequest
     * @param int $maxRetries
     * @return array
     * @throws Bitrix24APIException
     * @throws TokenRefreshException
     */
    protected function callBatchMultiple(Bitrix24BatchRequest $batchRequest, int $maxRetries = 0): array
    {
        $chunks = $batchRequest->splitIntoChunks();
        $totalChunks = count($chunks);

        Log::info('Разбиение большого батча на несколько запросов', [
            'total_commands' => $batchRequest->count(),
            'chunks' => $totalChunks,
            'max_per_chunk' => Bitrix24BatchRequest::getMaxBatchCount()
        ]);

        $aggregatedResults = [];
        $aggregatedErrors = [];
        $totalTime = 0;
        $successfulChunks = 0;

        foreach ($chunks as $index => $chunk) {
            try {
                $chunkResult = $this->executeSingleBatch($chunk, $maxRetries);

                // Агрегируем результаты
                foreach ($chunkResult['results'] as $key => $result) {
                    $aggregatedResults[$key] = $result;

                    // Собираем ошибки с привязкой к ключам команд
                    if (!empty($result['error'])) {
                        $aggregatedErrors[$key] = [
                            'command_key' => $key,
                            'error' => $result['error'],
                            'chunk_index' => $index + 1,
                            'chunk_total' => $totalChunks
                        ];
                    }
                }

                $totalTime += $chunkResult['time']['duration'] ?? 0;
                $successfulChunks++;

                Log::info(sprintf('Батч %d/%d выполнен успешно', $index + 1, $totalChunks), [
                    'commands_in_chunk' => $chunk->count(),
                    'errors_count' => count($aggregatedErrors)
                ]);

            } catch (Bitrix24APIException $e) {
                // При ошибке выполнения чанка логируем и продолжаем или прерываем
                Log::error(sprintf('Ошибка выполнения батча %d/%d', $index + 1, $totalChunks), [
                    'message' => $e->getMessage(),
                    'context' => $e->getContext()
                ]);

                // Если halt=true, прерываем выполнение
                if ($batchRequest->getHalt()) {
                    throw new Bitrix24APIException(
                        sprintf('Выполнение прервано на батче %d/%d: %s', $index + 1, $totalChunks, $e->getMessage()),
                        array_merge($e->getContext(), [
                            'chunk_index' => $index + 1,
                            'total_chunks' => $totalChunks,
                            'successful_chunks' => $successfulChunks,
                            'aggregated_errors' => $aggregatedErrors
                        ]),
                        0,
                        $e
                    );
                }

                // Добавляем информацию об ошибке всего чанка
                $aggregatedErrors['chunk_' . ($index + 1)] = [
                    'chunk_index' => $index + 1,
                    'chunk_total' => $totalChunks,
                    'error' => $e->getMessage(),
                    'context' => $e->getContext()
                ];
            }
        }

        $result = [
            'results' => $aggregatedResults,
            'time' => [
                'duration' => $totalTime,
                'chunks' => $totalChunks
            ],
            'total' => count($aggregatedResults),
            'chunks_executed' => $successfulChunks,
            'chunks_total' => $totalChunks,
        ];

        // Добавляем агрегированные ошибки, если они есть
        if (!empty($aggregatedErrors)) {
            $result['errors'] = $aggregatedErrors;
            $result['errors_count'] = count($aggregatedErrors);

            Log::warning('Пакетный запрос завершён с ошибками', [
                'total_errors' => count($aggregatedErrors),
                'successful_commands' => count($aggregatedResults) - count($aggregatedErrors),
                'total_commands' => count($aggregatedResults)
            ]);
        }

        return $result;
    }

    /**
     * Выполнить один пакетный запрос (до 50 команд)
     * 
     * @param Bitrix24BatchRequest $batchRequest
     * @param int $maxRetries
     * @param int $attempt
     * @return array
     * @throws Bitrix24APIException
     * @throws TokenRefreshException
     */
    protected function executeSingleBatch(Bitrix24BatchRequest $batchRequest, int $maxRetries = 0, int $attempt = 1): array
    {
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
                // Проверяем, является ли ошибка временной (5xx или timeout)
                $isTemporaryError = $response->status() >= 500 || $response->status() === 429;

                if ($isTemporaryError && $attempt <= $maxRetries) {
                    $waitTime = $this->calculateRetryDelay($attempt);

                    Log::warning('Временная ошибка API, повторная попытка', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'wait_seconds' => $waitTime,
                        'status' => $response->status()
                    ]);

                    sleep($waitTime);
                    return $this->executeSingleBatch($batchRequest, $maxRetries, $attempt + 1);
                }

                throw new Bitrix24APIException(
                    sprintf('HTTP ошибка при пакетном запросе: %d - %s', $response->status(), $response->body()),
                    [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'commands_count' => $batchRequest->count(),
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries
                    ]
                );
            }

            // Проверка на общую ошибку пакетного запроса
            if (isset($data['error'])) {
                // Некоторые ошибки можно повторить
                $isRetryableError = in_array($data['error'], ['QUERY_LIMIT_EXCEEDED', 'INTERNAL_ERROR']);

                if ($isRetryableError && $attempt <= $maxRetries) {
                    $waitTime = $this->calculateRetryDelay($attempt);

                    Log::warning('Временная ошибка Bitrix24 API, повторная попытка', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'wait_seconds' => $waitTime,
                        'error' => $data['error']
                    ]);

                    sleep($waitTime);
                    return $this->executeSingleBatch($batchRequest, $maxRetries, $attempt + 1);
                }

                throw new Bitrix24APIException(
                    sprintf(
                        'Ошибка пакетного API запроса: [%s] %s',
                        $data['error'],
                        $data['error_description'] ?? 'Нет описания'
                    ),
                    [
                        'error' => $data['error'],
                        'error_description' => $data['error_description'] ?? null,
                        'commands_count' => $batchRequest->count(),
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries
                    ]
                );
            }

            // Формируем результат с улучшенной обработкой ошибок
            $results = [];
            $commandKeys = array_keys($batchRequest->getCommands());
            $errorsFound = [];

            foreach ($commandKeys as $key) {
                $result = [
                    'result' => $data['result']['result'][$key] ?? null,
                    'error' => $data['result']['result_error'][$key] ?? null,
                    'time' => $data['result']['result_time'][$key] ?? null,
                    'total' => $data['result']['result_total'][$key] ?? 0,
                ];

                $results[$key] = $result;

                // Собираем ошибки с привязкой к ключам команд
                if (!empty($result['error'])) {
                    $errorsFound[$key] = [
                        'command_key' => $key,
                        'error' => $result['error'],
                        'error_description' => $result['error']['error_description'] ?? 'Нет описания'
                    ];
                }
            }

            $response = [
                'results' => $results,
                'time' => $data['time'] ?? null,
                'total' => count($results),
            ];

            // Добавляем информацию об ошибках, если они есть
            if (!empty($errorsFound)) {
                $response['errors'] = $errorsFound;
                $response['errors_count'] = count($errorsFound);
            }

            return $response;

        } catch (RequestException $e) {
            // Повторяем при connection timeout или network errors
            if ($attempt <= $maxRetries) {
                $waitTime = $this->calculateRetryDelay($attempt);

                Log::warning('Ошибка сети, повторная попытка', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'wait_seconds' => $waitTime,
                    'message' => $e->getMessage()
                ]);

                sleep($waitTime);
                return $this->executeSingleBatch($batchRequest, $maxRetries, $attempt + 1);
            }

            Log::error('Bitrix24 Batch Request Exception', [
                'message' => $e->getMessage(),
                'commands_count' => $batchRequest->count(),
                'attempt' => $attempt,
                'max_retries' => $maxRetries
            ]);

            throw new Bitrix24APIException(
                sprintf('Ошибка выполнения пакетного запроса: %s', $e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'commands_count' => $batchRequest->count(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
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
                'commands_count' => $batchRequest->count(),
                'attempt' => $attempt
            ]);

            throw new Bitrix24APIException(
                sprintf('Неожиданная ошибка при пакетном запросе: %s', $e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'commands_count' => $batchRequest->count(),
                    'attempt' => $attempt
                ],
                0,
                $e
            );
        }
    }

    /**
     * Рассчитать задержку перед повторной попыткой (exponential backoff)
     */
    protected function calculateRetryDelay(int $attempt): int
    {
        return min(pow(2, $attempt - 1), 30); // Максимум 30 секунд
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

    /**
     * Убедиться, что токен действителен, обновить если необходимо
     * 
     * @throws TokenRefreshException
     */
    protected function ensureValidToken(): void
    {
        if (!$this->portal) {
            return;
        }

        if (!$this->portal->needsTokenRefresh()) {
            return;
        }

        $this->refreshToken();
    }

    /**
     * Обновить токен доступа через API Битрикс24
     * 
     * @throws TokenRefreshException
     */
    protected function refreshToken(): void
    {
        if (!$this->portal) {
            throw new TokenRefreshException('Невозможно обновить токен: портал не установлен');
        }

        $clientId = config('services.bitrix24.client_id');
        $clientSecret = config('services.bitrix24.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new TokenRefreshException(
                'Не настроены client_id или client_secret для Битрикс24',
                ['portal_id' => $this->portal->id]
            );
        }

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->asForm()
                ->post('https://oauth.bitrix.info/oauth/token/', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $this->portal->refresh_token,
                ]);

            $data = $response->json();

            if (!$response->successful() || isset($data['error'])) {
                throw new TokenRefreshException(
                    sprintf(
                        'Ошибка обновления токена: %s',
                        $data['error_description'] ?? $data['error'] ?? 'Неизвестная ошибка'
                    ),
                    [
                        'portal_id' => $this->portal->id,
                        'domain' => $this->portal->domain,
                        'status' => $response->status(),
                        'error' => $data['error'] ?? null,
                        'error_description' => $data['error_description'] ?? null,
                    ]
                );
            }

            if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
                throw new TokenRefreshException(
                    'Некорректный ответ при обновлении токена: отсутствуют необходимые поля',
                    [
                        'portal_id' => $this->portal->id,
                        'response_data' => $data,
                    ]
                );
            }

            // Обновляем токены в базе данных
            $this->portal->updateTokens(
                $data['access_token'],
                $data['refresh_token'],
                $data['expires_in'] ?? 3600
            );

            // Обновляем токен в текущем экземпляре сервиса
            $this->accessToken = $data['access_token'];

            Log::info('Токен успешно обновлен', [
                'portal_id' => $this->portal->id,
                'domain' => $this->portal->domain,
                'expires_at' => $this->portal->expires_at->toDateTimeString(),
            ]);

        } catch (RequestException $e) {
            Log::error('Ошибка HTTP при обновлении токена', [
                'portal_id' => $this->portal->id,
                'message' => $e->getMessage(),
            ]);

            throw new TokenRefreshException(
                sprintf('Ошибка HTTP при обновлении токена: %s', $e->getMessage()),
                [
                    'portal_id' => $this->portal->id,
                    'exception' => get_class($e),
                ],
                0,
                $e
            );
        } catch (\Exception $e) {
            if ($e instanceof TokenRefreshException) {
                throw $e;
            }

            Log::error('Неожиданная ошибка при обновлении токена', [
                'portal_id' => $this->portal->id,
                'message' => $e->getMessage(),
            ]);

            throw new TokenRefreshException(
                sprintf('Неожиданная ошибка при обновлении токена: %s', $e->getMessage()),
                [
                    'portal_id' => $this->portal->id,
                    'exception' => get_class($e),
                ],
                0,
                $e
            );
        }
    }
}

