<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CvCapabilities
{
    public static function issueTemporary(string $path, int $minutes = 15, ?string $label = null): array
    {
        $token = Str::random(64);

        return self::store($token, $path, 'temporary', now()->addMinutes($minutes), $label);
    }

    public static function permanent(string $path, bool $replace = false): array
    {
        $existing = DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'permanent')->whereNull('label')->whereNull('revoked_at')->first();
        if ($existing && ! $replace) {
            return self::present($existing);
        }
        if ($existing) {
            DB::table('cv_access_tokens')->where('id', $existing->id)->update(['revoked_at' => now(), 'label_key' => null, 'updated_at' => now()]);
        }

        return self::store(Str::random(64), $path, 'permanent');
    }

    /** Reuse a named permanent CV grant, creating it without rotating other links. */
    public static function permanentForLabel(string $path, string $label): array
    {
        return self::namedGrant($path, 'permanent', $label);
    }

    /** Reuse the active preview grant; rotate it only after it expires. */
    public static function temporaryForLabel(string $path, string $label, int $minutes = 15): array
    {
        return self::namedGrant($path, 'temporary', $label, $minutes);
    }

    private static function namedGrant(string $path, string $kind, string $label, int $minutes = 15): array
    {
        $label = trim($label);
        if ($label === '') throw new \InvalidArgumentException('A named CV grant requires a label.');

        // The permanent key format predates this helper; preserve it. Temporary
        // previews use a distinct key so both kinds can share the same label.
        $keyMaterial = $kind === 'permanent' ? $path."\0".$label : $path."\0".$kind."\0".$label;
        $labelKey = hash_hmac('sha256', $keyMaterial, (string) config('app.key'));
        $lock = 'cv-grant:'.hash('sha256', $labelKey);

        // Serialize lookup and insertion before a token is selected for a PDF.
        // This uses the configured atomic cache lock (file-backed in production).
        return Cache::lock($lock, 30)->block(10, function () use ($path, $kind, $label, $labelKey, $minutes): array {
            $existing = DB::table('cv_access_tokens')
                ->where('profile_path', $path)
                ->where('label_key', $labelKey)
                ->whereNull('revoked_at')
                ->first();

            if ($existing && ($kind !== 'temporary' || ($existing->expires_at && now()->lt($existing->expires_at)))) {
                return self::present($existing) + ['created' => false];
            }

            if ($existing) {
                DB::table('cv_access_tokens')->where('id', $existing->id)->update([
                    'revoked_at' => now(), 'label_key' => null, 'updated_at' => now(),
                ]);
            }

            try {
                return self::store(
                    Str::random(64),
                    $path,
                    $kind,
                    $kind === 'temporary' ? now()->addMinutes($minutes) : null,
                    $label,
                    $labelKey,
                ) + ['created' => true];
            } catch (QueryException $exception) {
                // Keep a defensive fallback for deployments whose configured
                // cache lock is not shared across application workers.
                $existing = DB::table('cv_access_tokens')->where('profile_path', $path)->where('label_key', $labelKey)->whereNull('revoked_at')->first();
                if (! $existing || ($kind === 'temporary' && (! $existing->expires_at || now()->gte($existing->expires_at)))) throw $exception;
                return self::present($existing) + ['created' => false];
            }
        });
    }

    public static function issueNamed(string $path, string $kind, string $label, int $minutes = 15): array
    {
        if (! in_array($kind, ['temporary', 'permanent'], true)) throw new \InvalidArgumentException('Unsupported CV access grant kind.');
        return self::store(Str::random(64), $path, $kind, $kind === 'temporary' ? now()->addMinutes($minutes) : null, trim($label)) + ['created' => true];
    }

    public static function namedGrants(string $path): array
    {
        return DB::table('cv_access_tokens')->where('profile_path', $path)->whereNotNull('label')->orderByDesc('id')->get()
            ->map(function (object $record): array {
                try { $label = Crypt::decryptString($record->label); } catch (\Throwable) { $label = 'Bezeichnung nicht lesbar'; }
                return ['id' => $record->id, 'label' => $label, 'kind' => $record->kind, 'created_at' => $record->created_at,
                    'expires_at' => $record->expires_at, 'revoked_at' => $record->revoked_at];
            })->all();
    }

    public static function revokeGrant(string $path, int $id): bool
    {
        return DB::table('cv_access_tokens')->where('profile_path', $path)->where('id', $id)->whereNotNull('label')->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'label_key' => null, 'updated_at' => now()]) > 0;
    }

    public static function revokePermanent(string $path): bool
    {
        return DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'permanent')->whereNull('label')->whereNull('revoked_at')->update(['revoked_at' => now(), 'label_key' => null, 'updated_at' => now()]) > 0;
    }

    public static function allows(string $token, string $path): bool
    {
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
        return hash_equals($stored, $path);
    }

    public static function sessionAllows(Request $request, string $path): bool
    {
        $hash = $request->session()->get('cv_grants.'.hash('sha256', $path));
        if (! is_string($hash)) {
            return false;
        }
        $record = DB::table('cv_access_tokens')->where('token_hash', $hash)->whereNull('revoked_at')->first();
        if (! $record || ($record->expires_at && now()->isAfter($record->expires_at))) {
            return false;
        }

        return hash_equals($record->profile_path, $path);
    }

    public static function privatePdfToken(Request $request, string $path): ?string
    {
        $hash = $request->session()->get('cv_grants.'.hash('sha256', $path));
        if (! is_string($hash)) return null;

        $record = DB::table('cv_access_tokens')
            ->where('profile_path', $path)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();
        if (! $record || ($record->expires_at && now()->isAfter($record->expires_at))) return null;

        try {
            return Crypt::decryptString($record->encrypted_token);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    private static function store(string $token, string $path, string $kind, $expiresAt = null, ?string $label = null, ?string $labelKey = null): array
    {
        $identifier = Str::lower(Str::random(12));
        $id = DB::table('cv_access_tokens')->insertGetId([
            'profile_path' => $path, 'kind' => $kind, 'identifier' => $identifier,
            'token_hash' => hash('sha256', $token), 'encrypted_token' => Crypt::encryptString($token),
            'expires_at' => $expiresAt, 'label' => $label === null ? null : Crypt::encryptString($label), 'label_key' => $labelKey,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return self::present(DB::table('cv_access_tokens')->find($id));
    }

    private static function present(object $record): array
    {
        return [
            'id' => (int) $record->id,
            'token' => Crypt::decryptString($record->encrypted_token),
            'identifier' => $record->identifier,
            'kind' => $record->kind,
            'expires_at' => $record->expires_at,
            'label' => isset($record->label) && $record->label ? Crypt::decryptString($record->label) : null,
        ];
    }
}
