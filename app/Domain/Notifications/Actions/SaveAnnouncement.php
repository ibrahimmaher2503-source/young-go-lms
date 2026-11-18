<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Notifications\Announcement;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates or edits an announcement draft. The author must be allowed to update the course (admins, or an instructor of
 * it, through CoursePolicy). Both languages are required; a cohort must belong to the course. A published announcement is frozen.
 */
final class SaveAnnouncement
{
    public const int TITLE_MAX = 160;

    public const int BODY_MAX = 2000;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{course_id: int, cohort_id?: int|null, title_ar: string, title_en: string, body_ar: string, body_en: string} $data */
    public function __invoke(User $actor, array $data, ?Announcement $announcement = null): Announcement
    {
        $course = Course::query()->find($data['course_id']);
        if ($actor->getAttribute('status') !== 'active' || ! $course instanceof Course || ! $actor->can('update', $course)) {
            throw new AuthorizationException;
        }

        $values = [
            'title_ar' => $this->text($data['title_ar'], 'title_ar', self::TITLE_MAX),
            'title_en' => $this->text($data['title_en'], 'title_en', self::TITLE_MAX),
            'body_ar' => $this->text($data['body_ar'], 'body_ar', self::BODY_MAX),
            'body_en' => $this->text($data['body_en'], 'body_en', self::BODY_MAX),
        ];

        $cohortId = $data['cohort_id'] ?? null;
        if ($cohortId !== null && ! Cohort::query()->whereKey($cohortId)->where('course_id', $course->getKey())->exists()) {
            throw ValidationException::withMessages(['cohort_id' => __('notification_events.announcement_cohort_invalid')]);
        }

        return DB::transaction(function () use ($actor, $course, $cohortId, $values, $announcement): Announcement {
            $current = $announcement === null ? null : Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            if ($current !== null && ($current->isPublished() || (int) $current->course_id !== (int) $course->getKey())) {
                throw ValidationException::withMessages(['announcement' => __('notification_events.announcement_frozen')]);
            }

            $saved = $current ?? new Announcement;
            $before = $current?->only(['cohort_id', 'title_ar', 'title_en']);
            $saved->fill([...$values, 'course_id' => $course->getKey(), 'cohort_id' => $cohortId]);
            if ($current === null) {
                $saved->status = Announcement::DRAFT;
                $saved->author_id = $actor->getKey();
            }
            $saved->save();

            $recorded = $this->audit->record($current === null ? 'announcement.created' : 'announcement.updated', $saved, (int) $actor->getKey(), $before, [
                'course_id' => $course->getKey(), 'cohort_id' => $cohortId, 'title_en' => $values['title_en'],
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record announcement audit.');
            }

            return $saved;
        });
    }

    private function text(string $text, string $field, int $max): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => __('notification_events.announcement_text_invalid', ['max' => $max])]);
        }

        return $text;
    }
}
