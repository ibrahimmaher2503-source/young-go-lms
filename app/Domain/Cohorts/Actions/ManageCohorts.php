<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortAccess;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Create, edit, close and assign instructors to cohorts. Only live and hybrid courses have cohorts; recorded ones never need them. */
final class ManageCohorts
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, Course $course, array $data): Cohort
    {
        CohortAccess::authorize($actor, $course);
        if (! in_array($course->format, ['live', 'hybrid'], true)) {
            throw ValidationException::withMessages(['course' => __('cohorts.live_only')]);
        }
        $clean = $this->validated($data);

        $cohort = Cohort::query()->create([...$clean, 'course_id' => $course->getKey(), 'status' => Cohort::OPEN]);
        $this->log('cohort.created', $cohort, $actor, null, $clean);

        return $cohort;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Cohort $cohort, array $data): Cohort
    {
        CohortAccess::authorize($actor, $cohort->course);

        return DB::transaction(function () use ($actor, $cohort, $data): Cohort {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            $clean = $this->validated($data + [
                'starts_on' => $locked->starts_on?->toDateString(),
                'ends_on' => $locked->ends_on?->toDateString(),
                'enrollment_opens_on' => $locked->enrollment_opens_on?->toDateString(),
                'enrollment_closes_on' => $locked->enrollment_closes_on?->toDateString(),
                'name_translations' => $locked->name_translations,
            ]);
            if ($clean['capacity'] !== null && $clean['capacity'] < $locked->seatedCount()) {
                throw ValidationException::withMessages(['capacity' => __('cohorts.capacity_below_seated', ['count' => $locked->seatedCount()])]);
            }
            $before = $locked->only(array_keys($clean));
            $locked->update($clean);
            $this->log('cohort.updated', $locked, $actor, $before, $clean);

            return $locked;
        });
    }

    public function setStatus(User $actor, Cohort $cohort, string $status): void
    {
        CohortAccess::authorize($actor, $cohort->course);
        if (! in_array($status, [Cohort::OPEN, Cohort::CLOSED], true)) {
            throw ValidationException::withMessages(['status' => __('cohorts.invalid_status')]);
        }
        $before = $cohort->status;
        $cohort->update(['status' => $status]);
        $this->log('cohort.status_changed', $cohort, $actor, ['status' => $before], ['status' => $status]);
    }

    /** @param list<int> $instructorIds only people who are instructors of the course can lead its cohort */
    public function assignInstructors(User $actor, Cohort $cohort, array $instructorIds): void
    {
        CohortAccess::authorize($actor, $cohort->course);
        $wanted = array_values(array_unique($instructorIds));
        $allowed = $cohort->course->instructors()->whereIn('users.id', $wanted)->pluck('users.id')->all();
        if (count($allowed) !== count($wanted)) {
            throw ValidationException::withMessages(['instructors' => __('cohorts.instructor_not_on_course')]);
        }
        $cohort->instructors()->sync($allowed);
        $this->log('cohort.instructors_changed', $cohort, $actor, null, ['instructors' => $allowed]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, capacity: int|null, starts_on: string|null, ends_on: string|null, name_translations: array<string, string>|null, enrollment_opens_on: string|null, enrollment_closes_on: string|null}
     */
    private function validated(array $data): array
    {
        $clean = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'name_translations' => ['nullable', 'array:ar'],
            'name_translations.ar' => ['nullable', 'string', 'max:120'],
            'enrollment_opens_on' => ['nullable', 'date_format:Y-m-d'],
            'enrollment_closes_on' => ['nullable', 'date_format:Y-m-d', ...(! empty($data['enrollment_opens_on']) ? ['after_or_equal:enrollment_opens_on'] : [])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', ...(! empty($data['starts_on']) ? ['after_or_equal:starts_on'] : [])],
        ])->validate();

        $arabic = trim((string) ($clean['name_translations']['ar'] ?? ''));

        return [
            'name' => $clean['name'],
            'capacity' => $clean['capacity'] ?? null,
            'starts_on' => $clean['starts_on'] ?? null,
            'ends_on' => $clean['ends_on'] ?? null,
            'name_translations' => $arabic === '' ? null : ['ar' => $arabic],
            'enrollment_opens_on' => $clean['enrollment_opens_on'] ?? null,
            'enrollment_closes_on' => $clean['enrollment_closes_on'] ?? null,
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>  $after
     */
    private function log(string $event, Cohort $cohort, User $actor, ?array $before, array $after): void
    {
        if ($this->audit->record($event, $cohort, (int) $actor->getKey(), $before, $after) === null) {
            throw new RuntimeException('Unable to record cohort audit.');
        }
    }
}
