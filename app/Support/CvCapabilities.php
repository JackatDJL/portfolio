<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class CvCapabilities
{
    public static function issue(string $path, int $minutes = 15): string
    {
        $token = Str::random(64);
        Cache::put(self::key($token), ['path' => $path], now()->addMinutes($minutes));

        return $token;
    }

    public static function allows(string $token, string $path): bool
    {
        $expectedHash = (string) config('cv.access_token_hash');
        if (preg_match('/^[0-9a-f]{64}$/', $expectedHash) === 1
            && hash_equals($expectedHash, hash('sha256', $token))) {
            return true;
        }

        $capability = Cache::get(self::key($token));

        return is_array($capability) && hash_equals((string) ($capability['path'] ?? ''), $path);
    }

    private static function key(string $token): string
    {
        return 'cv-capability:'.hash('sha256', $token);
    }
}
