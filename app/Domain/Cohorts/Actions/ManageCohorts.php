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
        $clean = $this->validated($data);

        return DB::transaction(function () use ($actor, $cohort, $clean): Cohort {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            if ($clean['capacity'] !== null && $clean['capacity'] < $locked->seatedCount()) {
                throw ValidationException::withMessages(['capacity' => __('cohorts.capacity_below_seated', ['count' => $locked->seatedCount()])]);
            }
            $before = $locked->only(['name', 'capacity', 'starts_on', 'ends_on']);
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
     * @return array{name: string, capacity: int|null, starts_on: string|null, ends_on: string|null}
     */
    private function validated(array $data): array
    {
        /** @var array{name: string, capacity?: int|null, starts_on?: string|null, ends_on?: string|null} $clean */
        $clean = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ])->validate();

        return ['name' => $clean['name'], 'capacity' => $clean['capacity'] ?? null, 'starts_on' => $clean['starts_on'] ?? null, 'ends_on' => $clean['ends_on'] ?? null];
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
