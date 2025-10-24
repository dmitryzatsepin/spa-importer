<?php

namespace App\Http\Controllers;

use App\Models\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Обрабатывает первоначальный запрос на установку приложения от Битрикс24
     */
    public function install(Request $request)
    {
        // Валидация входящих параметров от Битрикс24
        $request->validate([
            'DOMAIN' => 'required|string',
            'PROTOCOL' => 'nullable|in:0,1',
            'LANG' => 'nullable|string',
            'APP_SID' => 'nullable|string',
            'MEMBER_ID' => 'nullable|string',
        ]);

        $domain = $request->input('DOMAIN');
        $protocol = $request->input('PROTOCOL') == 1 ? 'https' : 'http';

        // Формируем URL для OAuth авторизации
        $clientId = config('services.bitrix24.client_id') ?: env('BITRIX24_CLIENT_ID');
        $redirectUri = route('auth.callback');

        if (!$clientId) {
            return response()->json([
                'error' => 'Bitrix24 Client ID не настроен'
            ], 500);
        }

        // URL для авторизации OAuth 2.0
        $authUrl = "{$protocol}://{$domain}/oauth/authorize/?" . http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
        ]);

        Log::info('Bitrix24 install request', [
            'domain' => $domain,
            'auth_url' => $authUrl
        ]);

        // Перенаправляем на страницу авторизации Битрикс24
        return redirect($authUrl);
    }

    /**
     * Обрабатывает callback от Битрикс24 после авторизации
     */
    public function callback(Request $request)
    {
        // Валидация входящих параметров
        $request->validate([
            'code' => 'required|string',
            'domain' => 'required|string',
            'member_id' => 'required|string',
            'server_domain' => 'nullable|string',
        ]);

        $code = $request->input('code');
        $domain = $request->input('domain');
        $memberId = $request->input('member_id');

        try {
            // Обмениваем код на токены доступа
            $tokens = $this->exchangeCodeForTokens($code, $domain);

            // Получаем CLIENT_ID и CLIENT_SECRET для этого портала
            $clientId = config('services.bitrix24.client_id') ?: env('BITRIX24_CLIENT_ID');
            $clientSecret = config('services.bitrix24.client_secret') ?: env('BITRIX24_CLIENT_SECRET');

            // Сохраняем или обновляем информацию о портале
            $portal = Portal::updateOrCreate(
                ['member_id' => $memberId],
                [
                    'domain' => $domain,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_at' => Carbon::now()->addSeconds($tokens['expires_in']),
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]
            );

            Log::info('Bitrix24 installation successful', [
                'member_id' => $memberId,
                'domain' => $domain
            ]);

            // Сохраняем данные в сессию
            session([
                'portal_id' => $portal->id,
                'domain' => $domain,
                'member_id' => $memberId,
            ]);

            // Устанавливаем HttpOnly cookie с API-ключом, если он задан в env
            $apiKey = env('API_KEY');
            $response = redirect('/')->with('success', 'Приложение успешно установлено!');
            if ($apiKey) {
                $secure = config('app.env') === 'production';
                $response->withCookie(cookie('api_key', $apiKey, 60 * 24 * 7, null, null, $secure, true, false, 'Lax'));
            }

            // Перенаправляем на главную страницу приложения
            return $response;

        } catch (\Exception $e) {
            Log::error('Bitrix24 OAuth error', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'member_id' => $memberId
            ]);

            return response()->json([
                'error' => 'Ошибка при получении токенов доступа',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обменивает код авторизации на токены доступа
     */
    private function exchangeCodeForTokens(string $code, string $domain): array
    {
        $clientId = config('services.bitrix24.client_id') ?: env('BITRIX24_CLIENT_ID');
        $clientSecret = config('services.bitrix24.client_secret') ?: env('BITRIX24_CLIENT_SECRET');
        $redirectUri = route('auth.callback');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('Bitrix24 credentials не настроены в конфигурации');
        }

        // Определяем протокол (по умолчанию https)
        $protocol = 'https';

        // URL для получения токенов
        $tokenUrl = "{$protocol}://{$domain}/oauth/token/?" . http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $response = Http::get($tokenUrl);

        if (!$response->successful()) {
            throw new \Exception('Не удалось получить токены от Битрикс24: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
            throw new \Exception('Некорректный ответ от Битрикс24: ' . json_encode($data));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }
}

