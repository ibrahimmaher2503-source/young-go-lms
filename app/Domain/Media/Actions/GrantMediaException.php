<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\LessonPublishRule;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** An admin allows an open (T3) source on a paid lesson and records why. Audited, with the reason. */
final class GrantMediaException
{
    public const int REASON_MIN = 10;

    public const int REASON_MAX = 500;

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Lesson $lesson, string $reason): MediaAsset
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('panel.admin.access') || ! $actor->can('course.publish')) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('media.reason_invalid', ['min' => self::REASON_MIN, 'max' => self::REASON_MAX])]);
        }

        $media = $lesson->media;
        if ($media === null || LessonPublishRule::effectiveTier($media) !== MediaAsset::TIER_OPEN) {
            throw ValidationException::withMessages(['media' => __('media.no_exception_needed')]);
        }

        return DB::transaction(function () use ($actor, $media, $lesson, $reason): MediaAsset {
            $media->forceFill(['exception_reason' => $reason, 'exception_by' => $actor->getKey(), 'exception_at' => now()])->save();

            if ($this->audit->record('media.exception_granted', $lesson, (int) $actor->getKey(), null, ['provider' => $media->provider, 'tier' => MediaAsset::TIER_OPEN], ['reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record media audit.');
            }

            return $media;
        });
    }
}
