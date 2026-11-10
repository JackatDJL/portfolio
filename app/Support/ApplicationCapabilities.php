<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApplicationCapabilities
{
    /** Issued once. Never store the raw recipient secret, even encrypted. Reissue rotates. */
    public static function issue(string $id): string
    {
        $token = Str::random(64);
        DB::table('application_access_tokens')->updateOrInsert(['application_id' => $id], [
            'token_hash' => hash('sha256', $token), 'revoked_at' => null, 'expires_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    public static function revoke(string $id): void
    {
        DB::table('application_access_tokens')->where('application_id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public static function allowsHash(string $id, string $hash): bool
    {
        $record = DB::table('application_access_tokens')->where('application_id', $id)->whereNull('revoked_at')->first();

        return $record && hash_equals($record->token_hash, $hash) && (! $record->expires_at || now()->isBefore($record->expires_at));
    }

    public static function sessionAllows(Request $request, string $id): bool
    {
        $hash = $request->session()->get('application_grants.'.hash('sha256', $id));

        return is_string($hash) && self::allowsHash($id, $hash);
    }
}
