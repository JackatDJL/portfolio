<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CvCapabilities
{
    public static function issueTemporary(string $path, int $minutes = 15): array
    {
        $token = Str::random(64);

        return self::store($token, $path, 'temporary', now()->addMinutes($minutes));
    }

    public static function permanent(string $path, bool $replace = false): array
    {
        $existing = DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'permanent')->whereNull('revoked_at')->first();
        if ($existing && ! $replace) {
            return self::present($existing);
        }
        if ($existing) {
            DB::table('cv_access_tokens')->where('id', $existing->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
        }

        return self::store(Str::random(64), $path, 'permanent');
    }

    public static function revokePermanent(string $path): bool
    {
        return DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'permanent')->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]) > 0;
    }

    public static function allows(string $token, string $path): bool
    {
        $expectedHash = (string) config('cv.access_token_hash');
        if (preg_match('/^[0-9a-f]{64}$/', $expectedHash) === 1
            && hash_equals($expectedHash, hash('sha256', $token))) {
            return true;
        }

        $hash = hash('sha256', $token);
        $capability = DB::table('cv_access_tokens')->where('token_hash', $hash)->whereNull('revoked_at')->first();
        if (! $capability || ! self::sameScope($capability->profile_path, $path) || ($capability->expires_at && now()->isAfter($capability->expires_at))) {
            return false;
        }
        DB::table('cv_access_tokens')->where('id', $capability->id)->update(['last_used_at' => now(), 'updated_at' => now()]);

        return true;
    }

    private static function sameScope(string $stored, string $path): bool
    {
        if (hash_equals($stored, $path)) return true;
        $entry = \Statamic\Facades\Entry::find(basename($stored));
        return $entry && $entry->collectionHandle() === 'cv_profiles' && '/cv/'.$entry->slug() === $path;
    }

    public static function sessionAllows(\Illuminate\Http\Request $request, string $path): bool
    {
        $hash = $request->session()->get('cv_grants.'.hash('sha256', $path));
        if (!is_string($hash)) return false;
        $legacyHash = (string) config('cv.access_token_hash');
        if (preg_match('/^[0-9a-f]{64}$/', $legacyHash) && hash_equals($legacyHash, $hash)) return true;
        $record = DB::table('cv_access_tokens')->where('token_hash', $hash)->whereNull('revoked_at')->first();
        if (!$record || ($record->expires_at && now()->isAfter($record->expires_at))) return false;
        if (hash_equals($record->profile_path, $path)) return true;
        $legacy = \Statamic\Facades\Entry::find(basename($record->profile_path));
        return $legacy && $legacy->collectionHandle() === 'cv_profiles' && '/cv/'.$legacy->slug() === $path;
    }

    private static function store(string $token, string $path, string $kind, $expiresAt = null): array
    {
        $identifier = Str::lower(Str::random(12));
        $id = DB::table('cv_access_tokens')->insertGetId([
            'profile_path' => $path, 'kind' => $kind, 'identifier' => $identifier,
            'token_hash' => hash('sha256', $token), 'encrypted_token' => Crypt::encryptString($token),
            'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return self::present(DB::table('cv_access_tokens')->find($id));
    }

    private static function present(object $record): array
    {
        return [
            'token' => Crypt::decryptString($record->encrypted_token),
            'identifier' => $record->identifier,
            'kind' => $record->kind,
            'expires_at' => $record->expires_at,
        ];
    }
}
