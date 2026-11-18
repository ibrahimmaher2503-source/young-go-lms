<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ManageLearnerSessions
{
    public const string SESSION_KEY = 'learner_session_token';

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(User $learner, string $sessionId): string
    {
        // Parallel first requests from one browser must claim the same slot.
        $token = hash_hmac('sha256', $learner->getKey().':'.$sessionId, Config::string('app.key'));
        $hash = hash('sha256', $token);

        DB::transaction(function () use ($learner, $hash): void {
            User::query()->whereKey($learner->getKey())->lockForUpdate()->firstOrFail();
            if (DB::table('user_sessions')->where('token_hash', $hash)->exists()) {
                return;
            }
            DB::table('user_sessions')->insert([
                'user_id' => $learner->getKey(),
                'token_hash' => $hash,
                'last_seen_at' => now(),
            ]);

            $overflow = $this->active($learner)->orderByDesc('id')->pluck('id')->slice(max(1, Config::integer('identity.learner_session_limit')));
            if ($overflow->isNotEmpty()) {
                $count = DB::table('user_sessions')->whereIn('id', $overflow)->update(['revoked_at' => now()]);
                if ($this->audit->record('learner.session_evicted', $learner, (int) $learner->getKey(), context: ['count' => $count]) === null) {
                    throw new RuntimeException('Unable to audit learner session eviction.');
                }
            }
        }, 3);

        return $token;
    }

    public function touch(User $learner, string $token): bool
    {
        $session = $this->active($learner)->where('token_hash', hash('sha256', $token));
        if (! $session->exists()) {
            return false;
        }

        $session->where('last_seen_at', '<', now()->subMinute())->update(['last_seen_at' => now()]);

        return true;
    }

    public function end(User $learner, string $token): void
    {
        $this->active($learner)->where('token_hash', hash('sha256', $token))->update(['revoked_at' => now()]);
    }

    private function active(User $learner): Builder
    {
        return DB::table('user_sessions')->where('user_id', $learner->getKey())->whereNull('revoked_at')
            ->where('last_seen_at', '>=', now()->subMinutes(Config::integer('session.lifetime')));
    }
}
