<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApplicationCapabilities
{
    /** Issue a new independent bearer grant; only its one-time secret is returned. */
    public static function issue(string $id, string $label, mixed $expiresAt = null): array
    {
        $token = Str::random(64);
        $grantId = (string) Str::uuid();
        $now = now();

        DB::table('application_recipient_grants')->insert([
            'id' => $grantId,
            'application_id' => $id,
            'label' => Crypt::encryptString(trim($label)),
            'token_hash' => hash('sha256', $token),
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $expiresAt,
            'last_accessed_at' => null,
            'revoked_at' => null,
        ]);

        return ['id' => $grantId, 'token' => $token];
    }

    public static function revoke(string $id, string $grantId): bool
    {
        return DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->where('id', $grantId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) > 0;
    }

    public static function revokeAll(string $id): int
    {
        return DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public static function grants(string $id): array
    {
        return DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'created_at', 'expires_at', 'last_accessed_at', 'revoked_at'])
            ->map(fn (object $grant): array => [
                'id' => $grant->id,
                'label' => Crypt::decryptString($grant->label),
                'created_at' => $grant->created_at,
                'expires_at' => $grant->expires_at,
                'last_accessed_at' => $grant->last_accessed_at,
                'revoked_at' => $grant->revoked_at,
            ])->all();
    }

    public static function allowsHash(string $id, string $hash): bool
    {
        $record = self::activeGrantByHash($id, $hash);

        return $record !== null;
    }

    public static function grantIdForHash(string $id, string $hash): ?string
    {
        return self::activeGrantByHash($id, $hash)?->id;
    }

    private static function activeGrantByHash(string $id, string $hash): ?object
    {
        $record = DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $record || ! hash_equals($record->token_hash, $hash)
            || ($record->expires_at && ! now()->isBefore($record->expires_at))) {
            return null;
        }

        return $record;
    }

    /** Update audit metadata only after a successful secret exchange. */
    public static function recordAccess(string $id, string $hash): void
    {
        DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['last_accessed_at' => now(), 'updated_at' => now()]);
    }

    public static function sessionAllows(Request $request, string $id): bool
    {
        $sessionGrant = $request->session()->get('application_grants.'.hash('sha256', $id));
        if (! is_string($sessionGrant)) {
            return false;
        }

        $query = DB::table('application_recipient_grants')
            ->where('application_id', $id)
            ->whereNull('revoked_at');
        if (Str::isUuid($sessionGrant)) {
            $query->where('id', $sessionGrant);
        } elseif (preg_match('/^[a-f0-9]{64}$/D', $sessionGrant)) {
            // Existing sessions stored the hash before grants had independent IDs.
            $query->where('token_hash', $sessionGrant);
        } else {
            return false;
        }
        $record = $query->first();

        if (! $record || ($record->expires_at && ! now()->isBefore($record->expires_at))) {
            return false;
        }

        DB::table('application_recipient_grants')
            ->where('id', $record->id)
            ->whereNull('revoked_at')
            ->update(['last_accessed_at' => now(), 'updated_at' => now()]);

        return true;
    }
}
