<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\Assignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves the assignment rules: instructions, due date (entered in a timezone, stored in UTC), what learners may hand in,
 * the file size limit, the late policy and how many resubmissions are allowed. Late policy values and the way revisions
 * are counted are the current assignment configuration defaults.
 */
final class ConfigureAssignment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data instructions{ar,en}, due_at (local), timezone, allowed_types, max_size_kb, late_policy, late_penalty_percent, max_revisions */
    public function __invoke(User $actor, Assignment $assignment, array $data): Assignment
    {
        AssignmentAccess::authorize($actor, $assignment);
        $ceiling = (int) Config::get('assignments.ceiling_kilobytes');

        /** @var array{instructions?: array<string, string|null>|null, due_at?: string|null, timezone?: string|null, allowed_types: list<string>, max_size_kb?: int|null, late_policy: string, late_penalty_percent?: int|null, max_revisions?: int|null} $clean */
        $clean = Validator::make($data, [
            'instructions' => ['nullable', 'array'],
            'instructions.ar' => ['nullable', 'string', 'max:10000'],
            'instructions.en' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'allowed_types' => ['required', 'array', 'min:1'],
            'allowed_types.*' => ['string', 'in:'.implode(',', Assignment::TYPES)],
            'max_size_kb' => ['nullable', 'integer', 'min:100', 'max:'.$ceiling],
            'late_policy' => ['required', 'in:'.implode(',', Assignment::LATE_POLICIES)],
            'late_penalty_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_revisions' => ['nullable', 'integer', 'min:0', 'max:10'],
        ])->validate();

        if ($clean['late_policy'] === Assignment::LATE_PENALTY && ($clean['late_penalty_percent'] ?? null) === null) {
            throw ValidationException::withMessages(['late_penalty_percent' => __('assignment.errors.penalty_needed')]);
        }
        $due = $this->due($clean['due_at'] ?? null, $clean['timezone'] ?? null);
        if ($clean['late_policy'] !== Assignment::LATE_NOT_ALLOWED && $due === null) {
            throw ValidationException::withMessages(['late_policy' => __('assignment.errors.late_needs_due')]);
        }

        $before = $assignment->only(['due_at', 'allowed_types', 'max_size_kb', 'late_policy', 'late_penalty_percent', 'max_revisions']);
        $instructions = array_filter(array_map(static fn ($v): string => is_string($v) ? trim($v) : '', (array) ($clean['instructions'] ?? [])), static fn (string $v): bool => $v !== '');
        $assignment->update([
            'instructions' => $instructions === [] ? null : $instructions, 'due_at' => $due, 'allowed_types' => array_values(array_unique($clean['allowed_types'])),
            'max_size_kb' => $clean['max_size_kb'] ?? null, 'late_policy' => $clean['late_policy'],
            'late_penalty_percent' => $clean['late_policy'] === Assignment::LATE_PENALTY ? $clean['late_penalty_percent'] : null, 'max_revisions' => (int) ($clean['max_revisions'] ?? 0),
        ]);

        if ($this->audit->record('assignment.configured', $assignment, (int) $actor->getKey(), $before, $assignment->only(array_keys($before))) === null) {
            throw new RuntimeException('Unable to record assignment audit.');
        }

        return $assignment;
    }

    private function due(?string $local, ?string $timezone): ?CarbonImmutable
    {
        if ($local === null || trim($local) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($local, $timezone ?: 'UTC')->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_at' => __('assignment.errors.due_invalid')]);
        }
    }
}
