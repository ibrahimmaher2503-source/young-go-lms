<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Certificates\Certificate;
use App\Domain\Completion\CourseCompletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Staff issue a fresh certificate (new number and token) for a completion: it voids the live one first when there is one,
 * and also covers a completion that never got a certificate. Explicit, audited, reason required, course update gate.
 * The learner is not notified again (the issuance notice is once per completion).
 */
final class ReissueCertificate
{
    public function __construct(private readonly IssueCertificate $issue, private readonly RevokeCertificate $revoke) {}

    public function __invoke(User $actor, CourseCompletion $completion, string $reason): Certificate
    {
        $completion->loadMissing('course');
        Gate::forUser($actor)->authorize('update', $completion->course);
        $reason = RevokeCertificate::cleanReason($reason);

        return DB::transaction(function () use ($actor, $completion, $reason): Certificate {
            $replaces = null;
            $live = Certificate::query()->where('course_completion_id', $completion->getKey())->whereNull('revoked_at')->lockForUpdate()->first();
            if ($live instanceof Certificate) {
                $this->revoke->revoke($actor, $live, $reason);
                $replaces = $live->number;
            }

            return $this->issue->create($completion, 'certificate.reissued', (int) $actor->getKey(), ['reason' => $reason, 'replaces' => $replaces]);
        });
    }
}
