<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $expiresAt = $expiresAt === null
            ? null
            : Carbon::parse($expiresAt)->setTimezone(config('app.timezone'))->toDateTimeString();
        $label = trim($label);

        DB::table('application_recipient_grants')->insert([
            'id' => $grantId,
            'application_id' => $id,
            'label' => Crypt::encryptString($label),
            'token_hash' => hash('sha256', $token),
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $expiresAt,
            'last_accessed_at' => null,
            'revoked_at' => null,
        ]);

        return [
            'id' => $grantId,
            'token' => $token,
            'label' => $label,
            'created_at' => $now->toIso8601String(),
            'expires_at' => self::isoTimestamp($expiresAt),
            'last_accessed_at' => null,
            'revoked_at' => null,
        ];
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
            ->map(function (object $grant): array {
                try {
                    $label = Crypt::decryptString($grant->label);
                } catch (DecryptException) {
                    $label = 'Bezeichnung nicht lesbar';
                }

                return [
                    'id' => $grant->id,
                    'label' => $label,
                    'created_at' => self::isoTimestamp($grant->created_at),
                    'expires_at' => self::isoTimestamp($grant->expires_at),
                    'last_accessed_at' => self::isoTimestamp($grant->last_accessed_at),
                    'revoked_at' => self::isoTimestamp($grant->revoked_at),
                ];
            })->all();
    }

    /** Recent recipient activity, with no network address or browser fingerprint. */
    public static function events(string $id): array
    {
        return DB::table('application_recipient_events as events')
            ->join('application_recipient_grants as grants', 'grants.id', '=', 'events.grant_id')
            ->where('events.application_id', $id)
            ->orderByDesc('events.id')
            ->limit(100)
            ->get(['events.grant_id', 'events.event', 'events.path', 'events.created_at', 'grants.label'])
            ->map(function (object $event): array {
                try {
                    $label = Crypt::decryptString($event->label);
                } catch (DecryptException) {
                    $label = 'Bezeichnung nicht lesbar';
                }

                return [
                    'grant_label' => $label,
                    'event' => $event->event,
                    'path' => $event->path,
                    'occurred_at' => self::isoTimestamp($event->created_at),
                ];
            })->all();
    }

    public static function recordEvent(string $applicationId, string $grantId, string $event, ?string $path = null): void
    {
        if (! in_array($event, ['link_opened', 'page_view', 'pdf_requested'], true)) {
            throw new \InvalidArgumentException('Unsupported application recipient event.');
        }

        DB::table('application_recipient_events')->insert([
            'application_id' => $applicationId,
            'grant_id' => $grantId,
            'event' => $event,
            'path' => $path,
            'created_at' => now(),
        ]);

        // Keep only the latest 100 events for each recipient link. Deleting
        // against a limited subquery avoids dialect-specific OFFSET-only SQL.
        $retainedIds = DB::table('application_recipient_events')
            ->where('grant_id', $grantId)
            ->orderByDesc('id')
            ->limit(100)
            ->select('id');

        DB::table('application_recipient_events')
            ->where('grant_id', $grantId)
            ->whereNotIn('id', $retainedIds)
            ->delete();
    }

    public static function recordSessionEvent(Request $request, string $applicationId, string $event, ?string $path = null): bool
    {
        $grantId = self::sessionGrantId($request, $applicationId);
        if (! $grantId) {
            return false;
        }

        self::recordEvent($applicationId, $grantId, $event, $path);

        return true;
    }

    /** Return database timestamps with an explicit offset for browser-safe comparisons. */
    private static function isoTimestamp(?string $timestamp): ?string
    {
        return $timestamp === null
            ? null
            : Carbon::parse($timestamp, config('app.timezone'))->toIso8601String();
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

    /** Resolve a legacy fragment-only application link without trusting its old path. */
    public static function applicationIdForHash(string $hash): ?string
    {
        $record = DB::table('application_recipient_grants')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $record || ! hash_equals($record->token_hash, $hash)
            || ($record->expires_at && ! now()->isBefore($record->expires_at))) {
            return null;
        }

        return $record->application_id;
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
        return self::sessionGrantId($request, $id) !== null;
    }

    public static function sessionGrantId(Request $request, string $id): ?string
    {
        $sessionGrant = $request->session()->get('application_grants.'.hash('sha256', $id));
        if (! is_string($sessionGrant)) {
            return null;
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
            return null;
        }
        $record = $query->first();

        if (! $record || ($record->expires_at && ! now()->isBefore($record->expires_at))) {
            return null;
        }

        return $record->id;
    }

    public static function hasAuthorizedApplication(Request $request): bool
    {
        foreach ($request->session()->get('application_grants', []) as $hash => $grantId) {
            if (is_string($grantId) && preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                // Legacy hash-based session
                $applicationId = substr($hash, 0, 36);
                if (Str::isUuid($applicationId)) {
                    return true;
                }
            } elseif (Str::isUuid($hash)) {
                // New application ID based session
                return true;
            }
        }
        return false;
    }

    public static function authorizedApplicationId(Request $request): ?string
    {
        foreach ($request->session()->get('application_grants', []) as $hash => $grantId) {
            if (is_string($grantId) && preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                // Legacy hash-based session - the hash is sha256 of the application ID
                $applicationId = substr($hash, 0, 36);
                if (Str::isUuid($applicationId)) {
                    return $applicationId;
                }
            } elseif (Str::isUuid($hash)) {
                // New application ID based session
                return $hash;
            }
        }
        return null;
    }
}
