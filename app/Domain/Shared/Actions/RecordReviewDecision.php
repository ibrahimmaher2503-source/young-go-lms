<?php

declare(strict_types=1);

namespace App\Domain\Shared\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared approve/reject flow: authorization recheck, reason rules, the owning
 * feature's domain mutation and the audit entry commit together or not at all.
 */
final class RecordReviewDecision
{
    public const string APPROVE = 'approved';

    public const string REJECT = 'rejected';

    public const int REASON_MIN = 10;

    public const int REASON_MAX = 1000;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  Closure(Model, User, ?string): void  $mutation  the owning feature's domain action
     */
    public function handle(User $actor, Model $subject, string $ability, string $eventPrefix, string $decision, ?string $reason, Closure $mutation): void
    {
        if (! in_array($decision, [self::APPROVE, self::REJECT], true) || blank($eventPrefix)) {
            throw new InvalidArgumentException('Unknown review decision or event prefix.');
        }

        if ($actor->getAttribute('status') !== 'active' || ! Gate::forUser($actor)->allows($ability, $subject)) {
            throw new AuthorizationException;
        }

        $reason = $reason === null ? null : trim($reason);
        $reason = $reason === '' ? null : $reason;
        $length = $reason === null ? 0 : mb_strlen($reason);
        if (($decision === self::REJECT && $length < self::REASON_MIN) || $length > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('validation.between.string', [
                'attribute' => __('common.review_reason'), 'min' => self::REASON_MIN, 'max' => self::REASON_MAX,
            ])]);
        }

        DB::transaction(function () use ($actor, $subject, $eventPrefix, $decision, $reason, $mutation): void {
            $mutation($subject, $actor, $reason);

            if ($this->audit->record("{$eventPrefix}.{$decision}", $subject, (int) $actor->getKey(), null, ['decision' => $decision], ['reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record review decision audit.');
            }
        });
    }
}
