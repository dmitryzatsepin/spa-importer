<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Portal extends Model
{
    use HasFactory;
    protected $fillable = [
        'member_id',
        'domain',
        'access_token',
        'refresh_token',
        'expires_at',
        'client_id',
        'client_secret',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Проверяет, истек ли токен доступа
     */
    public function isTokenExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Проверяет, требуется ли обновление токена
     * (токен истек или истекает в ближайшие 60 секунд)
     */
    public function needsTokenRefresh(int $bufferSeconds = 60): bool
    {
        if (!$this->expires_at) {
            return true;
        }

        return $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }

    /**
     * Обновляет токены доступа
     */
    public function updateTokens(string $accessToken, string $refreshToken, int $expiresIn): void
    {
        $this->access_token = $accessToken;
        $this->refresh_token = $refreshToken;
        $this->expires_at = Carbon::now()->addSeconds($expiresIn);
        $this->save();
    }

    /**
     * Находит портал по member_id
     */
    public static function findByMemberId(string $memberId): ?self
    {
        return self::where('member_id', $memberId)->first();
    }

    /**
     * Находит портал по домену
     */
    public static function findByDomain(string $domain): ?self
    {
        return self::where('domain', $domain)->first();
    }
}

