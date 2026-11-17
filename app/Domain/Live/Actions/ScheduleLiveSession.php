<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Live\LiveAccess;
use App\Domain\Live\MeetingProvider;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Schedules a live session as a draft curriculum item at the end of a section. Start and end arrive as wall-clock times
 * in the scheduler timezone and are stored in UTC. Live items are never assistant-eligible (the AI hook, ED-032).
 */
final class ScheduleLiveSession
{
    public function __construct(private readonly AuditLogger $audit, private readonly MeetingProvider $meetings) {}

    /**
     * @param  array<string, mixed>  $data  title.ar/en, description, starts_at, ends_at (local), timezone, provider, meeting_url, join_window_minutes, cohort_id, instructor_id
     */
    public function __invoke(User $actor, CourseSection $section, array $data): CurriculumItem
    {
        $course = $section->course;
        LiveAccess::authorize($actor, $course);
        CurriculumAccess::authorize($actor, $course, CurriculumAccess::CREATE);
        if ($section->archived_at !== null) {
            throw ValidationException::withMessages(['section' => __('curriculum.section_archived')]);
        }

        $fields = self::validated($data);
        [$startsAt, $endsAt] = self::utcRange($fields['starts_at'], $fields['ends_at'], $fields['timezone']);
        $url = $this->meetings->validateUrl($fields['provider'], $fields['meeting_url'] ?? null);

        if (($fields['cohort_id'] ?? null) !== null && ! Cohort::query()->whereKey($fields['cohort_id'])->where('course_id', $course->getKey())->exists()) {
            throw ValidationException::withMessages(['cohort_id' => __('live.cohort_not_on_course')]);
        }
        if (($fields['instructor_id'] ?? null) !== null && ! $course->instructors()->whereKey($fields['instructor_id'])->exists()) {
            throw ValidationException::withMessages(['instructor_id' => __('live.instructor_not_on_course')]);
        }

        return DB::transaction(function () use ($actor, $section, $course, $fields, $startsAt, $endsAt, $url): CurriculumItem {
            CourseSection::query()->whereKey($section->getKey())->lockForUpdate()->first();
            $session = LiveSession::query()->create([
                'course_id' => $course->getKey(), 'cohort_id' => $fields['cohort_id'] ?? null, 'instructor_id' => $fields['instructor_id'] ?? null,
                'title' => array_filter($fields['title']), 'description' => array_filter($fields['description'] ?? []),
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => $fields['timezone'], 'provider' => $fields['provider'],
                'meeting_url' => $url, 'join_window_minutes' => (int) ($fields['join_window_minutes'] ?? 15), 'status' => LiveSession::SCHEDULED,
            ]);
            $sort = (int) CurriculumItem::query()->where('section_id', $section->getKey())->max('sort') + 1;
            $item = CurriculumItem::query()->create([
                'course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => $sort,
                'itemable_type' => 'live_session', 'itemable_id' => $session->getKey(), 'status' => CurriculumItem::STATUS_DRAFT, 'ai_enabled' => false,
            ]);

            if ($this->audit->record('live_session.scheduled', $session, (int) $actor->getKey(), null, ['starts_at' => $startsAt->toIso8601String(), 'cohort_id' => $session->cohort_id]) === null) {
                throw new RuntimeException('Unable to record live session audit.');
            }

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: array<string, mixed>, description?: array<string, mixed>|null, starts_at: string, ends_at: string, timezone: string, provider: string, meeting_url?: string|null, join_window_minutes?: int|null, cohort_id?: int|null, instructor_id?: int|null}
     */
    public static function validated(array $data): array
    {
        $title = CurriculumAccess::validTitle($data);
        /** @var array{description?: array<string, mixed>|null, starts_at: string, ends_at: string, timezone: string, provider: string, meeting_url?: string|null, join_window_minutes?: int|null, cohort_id?: int|null, instructor_id?: int|null} $clean */
        $clean = Validator::make($data, [
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'provider' => ['required', 'string'],
            'meeting_url' => ['nullable', 'string', 'max:2048'],
            'join_window_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'cohort_id' => ['nullable', 'integer'],
            'instructor_id' => ['nullable', 'integer'],
        ])->validate();

        return ['title' => $title, ...$clean];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} start and end in UTC
     */
    public static function utcRange(string $startsAt, string $endsAt, string $timezone): array
    {
        try {
            $zone = new DateTimeZone($timezone);
            $start = CarbonImmutable::parse($startsAt, $zone)->utc();
            $end = CarbonImmutable::parse($endsAt, $zone)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => __('live.time_invalid')]);
        }
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ends_at' => __('live.ends_before_start')]);
        }

        return [$start, $end];
    }
}
