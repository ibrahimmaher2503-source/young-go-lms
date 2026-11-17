<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\LinkCodeHasher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IssueLinkCode
{
    public const int VALID_HOURS = 48;

    public function __construct(private readonly AuditLogger $audit) {}

    /** Returns the plain code once; only its keyed hash is stored. */
    public function handle(User $learner): string
    {
        if ($learner->getAttribute('status') !== 'active' || ! $learner->hasRole('student')) {
            throw new AuthorizationException;
        }

        $code = LinkCodeHasher::generate();

        DB::transaction(function () use ($learner, $code): void {
            DB::table('guardian_link_codes')->where('learner_id', $learner->getKey())->whereNull('used_at')->delete();
            DB::table('guardian_link_codes')->insert([
                'learner_id' => $learner->getKey(),
                'code_hash' => LinkCodeHasher::hash($code),
                'expires_at' => now()->addHours(self::VALID_HOURS),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($this->audit->record('guardian.link_code_issued', $learner, (int) $learner->getKey()) === null) {
                throw new RuntimeException('Unable to record link code audit.');
            }
        });

        return $code;
    }
}
