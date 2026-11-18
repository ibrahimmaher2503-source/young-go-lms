<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deters account sharing (YG-GAP-SEC-001): at most N concurrent sessions per learner (the oldest is signed out when a
 * new one starts) and at most M registered devices (a new device over the limit is refused until staff reset them).
 * Only hashes of session ids and device tokens are kept. Staff accounts are not limited here.
 */
final class SessionLimiter
{
    public const string OK = 'ok';

    public const string SIGNED_OUT = 'signed_out';

    public const string DEVICE_LIMIT = 'device_limit';

    public function __construct(private readonly AuditLogger $audit) {}

    public function applies(User $user): bool
    {
        return (bool) config('security.sessions.enabled') && $user->hasAnyRole((array) config('security.sessions.roles'));
    }

    /** @return self::OK|self::SIGNED_OUT|self::DEVICE_LIMIT */
    public function check(User $user, string $sessionId, string $deviceToken, string $ip, string $userAgent, bool $register = true): string
    {
        $hash = hash('sha256', $sessionId);

        return DB::transaction(function () use ($user, $hash, $deviceToken, $ip, $userAgent, $register): string {
            // Serialise new and resumed sessions before reading their state or enforcing the limit.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $session = UserSession::query()->where('session_hash', $hash)->first();
            if ($session !== null) {
                if ($session->user_id !== $user->getKey()) {
                    if (! $register) {
                        return self::SIGNED_OUT;
                    }
                    // The browser session id now belongs to another account: treat it as a fresh session below.
                    $session->delete();
                } elseif ($session->signed_out_at !== null) {
                    return self::SIGNED_OUT;
                } else {
                    $this->touch($session, $ip);
                    $this->enforceConcurrentLimit($user, $hash);

                    return self::OK;
                }
            }

            if (! $register) {
                return self::SIGNED_OUT;
            }

            $device = UserDevice::query()->where('user_id', $user->getKey())->where('token_hash', hash('sha256', $deviceToken))->first();
            if ($device === null) {
                if (UserDevice::query()->where('user_id', $user->getKey())->count() >= (int) config('security.sessions.max_devices')) {
                    $this->audit->record('security.device_refused', $user, (int) $user->getKey(), null, null, ['ip' => $ip]);

                    return self::DEVICE_LIMIT;
                }
                $device = UserDevice::query()->create(['user_id' => $user->getKey(), 'token_hash' => hash('sha256', $deviceToken), 'label' => $this->label($userAgent), 'last_ip' => $ip, 'last_seen_at' => now()]);
            } else {
                $device->forceFill(['last_seen_at' => now(), 'last_ip' => $ip])->save();
            }

            UserSession::query()->create([
                'user_id' => $user->getKey(), 'device_id' => $device->getKey(), 'session_hash' => $hash, 'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 255), 'last_active_at' => now(),
            ]);

            $this->enforceConcurrentLimit($user, $hash);

            return self::OK;
        });
    }

    public function rotate(User $user, string $previousId, string $sessionId): bool
    {
        return DB::transaction(function () use ($user, $previousId, $sessionId): bool {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            return UserSession::query()->where('user_id', $user->getKey())
                ->where('session_hash', hash('sha256', $previousId))->whereNull('signed_out_at')
                ->update(['session_hash' => hash('sha256', $sessionId)]) === 1;
        });
    }

    /** Marks a session ended (normal sign-out). */
    public function end(string $sessionId, string $reason = UserSession::LOGOUT): void
    {
        UserSession::query()->where('session_hash', hash('sha256', $sessionId))->whereNull('signed_out_at')
            ->update(['signed_out_at' => now(), 'signed_out_reason' => $reason]);
    }

    public function newDeviceToken(): string
    {
        return Str::random(48);
    }

    private function enforceConcurrentLimit(User $user, string $currentHash): void
    {
        $max = max(1, (int) config('security.sessions.max_concurrent'));
        $active = UserSession::query()->where('user_id', $user->getKey())->whereNull('signed_out_at')
            ->where('last_active_at', '>=', now()->subMinutes((int) config('security.sessions.idle_minutes')))
            ->orderBy('last_active_at')->orderBy('id')->get();

        $overflow = $active->count() - $max;
        if ($overflow <= 0) {
            return;
        }

        foreach ($active->reject(fn (UserSession $s): bool => $s->session_hash === $currentHash)->take($overflow) as $oldest) {
            $oldest->forceFill(['signed_out_at' => now(), 'signed_out_reason' => UserSession::LIMIT])->save();
            $this->audit->record('security.session_limit_signout', $user, (int) $user->getKey(), null, null, ['session_id' => $oldest->getKey()]);
        }
    }

    private function touch(UserSession $session, string $ip): void
    {
        $now = now();
        if ($session->last_active_at->diffInSeconds($now) >= (int) config('security.sessions.touch_seconds')
            || $session->last_active_at->lt($now->copy()->subMinutes((int) config('security.sessions.idle_minutes')))) {
            $session->forceFill(['last_active_at' => now(), 'ip' => $ip])->save();
        }
    }

    private function label(string $userAgent): string
    {
        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Device',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return $os.' · '.$browser;
    }
}
