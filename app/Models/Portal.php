<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Portal extends Model
{
    protected $fillable = [
        'member_id',
        'domain',
        'access_token',
        'refresh_token',
        'expires_at',
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

