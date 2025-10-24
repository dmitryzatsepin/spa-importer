<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = env('API_KEY');

        if (!$expected) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not configured',
            ], 500);
        }

        $provided = $request->header('X-API-Key') ?: $request->cookie('api_key');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}


