<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Course wizard steps 1 (Basic) and 2 (Information). A draft may be saved at any point with
 * incomplete data ($complete = false); passing a step ($complete = true) enforces its required fields.
 * Ownership, status and featured are changed only by users who hold the matching permission.
 */
final class SaveCourseWizard
{
    public const int LAST_STEP = 2;

    public const array LEVELS = ['beginner', 'intermediate', 'advanced', 'all'];

    public const array FORMATS = ['recorded', 'live', 'hybrid'];

    public const array CONTENT_LANGUAGES = ['ar', 'en', 'both'];

    /** Statuses the wizard may set; publishing and review go through their own actions. */
    public const array WIZARD_STATUSES = ['draft', 'private', 'upcoming', 'suspended', 'archived'];

    private const array OWNERSHIP_FIELDS = ['owner_type', 'owner_instructor_id'];

    private const array STATUS_FIELDS = ['status', 'featured'];

    private const int MAX_ROWS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function __invoke(User $actor, array $data, ?Course $course = null, int $step = 1, bool $complete = false): Course
    {
        if ($course === null) {
            Gate::forUser($actor)->authorize('course.create');
        } else {
            Gate::forUser($actor)->authorize('update', $course);
        }

        $data = $this->guardPrivileged($actor, $course, $data);
        $clean = $this->validate($data, $course, $complete ? $step : null);

        return DB::transaction(function () use ($actor, $clean, $course, $step, $complete): Course {
            $record = $course ?? new Course;
            $before = $record->exists ? Arr::only($record->getAttributes(), ['owner_type', 'owner_instructor_id', 'status', 'featured']) : null;
            $record->fill($clean['attributes']);
            $record->slug_ar = $clean['slugs']['slug_ar'] ?? $record->slug_ar ?? $this->uniqueSlug('slug_ar', $record, $record->getTranslation('title', 'ar', false) ?: $record->getTranslation('title', 'en', false), 'ar');
            $record->slug_en = $clean['slugs']['slug_en'] ?? $record->slug_en ?? $this->uniqueSlug('slug_en', $record, $record->getTranslation('title', 'en', false) ?: $record->getTranslation('title', 'ar', false), 'en');

            $completed = is_array($record->wizard_completed) ? $record->wizard_completed : [];
            if ($complete) {
                $completed[(string) $step] = true;
            }
            $record->wizard_completed = $completed;
            $record->wizard_step = min(self::LAST_STEP, max((int) $record->wizard_step, $complete ? $step + 1 : $step));
            $created = ! $record->exists;
            $record->save();

            $this->auditChange($actor, $record, $created, $before);

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function guardPrivileged(User $actor, ?Course $course, array $data): array
    {
        // A form that never touched these fields sends them empty; that means "no change", not a value.
        foreach (['owner_type', 'status', 'featured'] as $optional) {
            if (array_key_exists($optional, $data) && ($data[$optional] === null || $data[$optional] === '')) {
                unset($data[$optional]);
            }
        }

        $canOwnership = $actor->can('panel.admin.access') && $actor->can('course.create');
        $canStatus = $actor->can('panel.admin.access') && $actor->can('course.publish');

        foreach ([[self::OWNERSHIP_FIELDS, $canOwnership], [self::STATUS_FIELDS, $canStatus]] as [$fields, $allowed]) {
            foreach ($fields as $field) {
                if (! array_key_exists($field, $data) || $allowed) {
                    continue;
                }

                // A resubmitted unchanged value is harmless; a different value is a tampering attempt.
                $current = $course?->getAttribute($field);
                $current = $current instanceof CourseStatus ? $current->value : $current;
                if ($course === null || $data[$field] != $current) {
                    throw new AuthorizationException;
                }
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, slugs: array<string, string>}
     */
    private function validate(array $data, ?Course $course, ?int $strictStep): array
    {
        $strict1 = $strictStep === 1;
        $required = static fn (bool $on): array => $on ? ['required'] : ['nullable'];
        $row = static fn (int $max): array => ['nullable', 'string', 'max:'.$max];

        $rules = [
            'title' => ['nullable', 'array'],
            'title.ar' => $row(200), 'title.en' => $row(200),
            'short_description' => ['nullable', 'array'],
            'short_description.ar' => $row(300), 'short_description.en' => $row(300),
            'description' => ['nullable', 'array'],
            'description.ar' => $row(10000), 'description.en' => $row(10000),
            'slug_ar' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('courses', 'slug_ar')->ignore($course?->getKey())],
            'slug_en' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('courses', 'slug_en')->ignore($course?->getKey())],
            'category_id' => [...$required($strict1), 'integer', Rule::exists('categories', 'id')],
            'level' => [...$required($strict1), Rule::in(self::LEVELS)],
            'content_language' => [...$required($strict1), Rule::in(self::CONTENT_LANGUAGES)],
            'format' => [...$required($strict1), Rule::in(self::FORMATS)],
            'min_age' => ['nullable', 'integer', 'between:0,120'],
            'max_age' => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'owner_type' => ['sometimes', Rule::in([Course::OWNER_ACADEMY, Course::OWNER_INDEPENDENT])],
            'owner_instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['sometimes', Rule::in(self::WIZARD_STATUSES)],
            'featured' => ['sometimes', 'boolean'],
            'faqs' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'faqs.*.question.ar' => $row(300), 'faqs.*.question.en' => $row(300),
            'faqs.*.answer.ar' => $row(2000), 'faqs.*.answer.en' => $row(2000),
            'requirements' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'requirements.*.text.ar' => $row(500), 'requirements.*.text.en' => $row(500),
            'outcomes' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'outcomes.*.text.ar' => $row(500), 'outcomes.*.text.en' => $row(500),
        ];

        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data, $strict1): void {
            $title = (array) ($data['title'] ?? []);
            if ($strict1 && blank($title['ar'] ?? null) && blank($title['en'] ?? null)) {
                $validator->errors()->add('title.ar', __('validation.required', ['attribute' => __('courses.name')]));
            }
            if (($data['owner_type'] ?? null) === Course::OWNER_INDEPENDENT && blank($data['owner_instructor_id'] ?? null)) {
                $validator->errors()->add('owner_instructor_id', __('validation.required', ['attribute' => __('courses.owner_instructor')]));
            }
        });

        $validated = $validator->validate();

        if (! blank($validated['owner_instructor_id'] ?? null)) {
            $owner = User::query()->find($validated['owner_instructor_id']);
            if ($owner === null || ! $owner->hasAnyRole(['instructor_academy', 'instructor_independent'])) {
                throw ValidationException::withMessages(['owner_instructor_id' => __('courses.invalid_owner')]);
            }
        }

        $attributes = Arr::only($validated, [
            'title', 'short_description', 'description', 'category_id', 'level', 'content_language', 'format',
            'min_age', 'max_age', 'owner_type', 'owner_instructor_id', 'status', 'featured',
        ]);
        foreach (['title', 'short_description', 'description'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $attributes[$field]), static fn ($v) => $v !== null && $v !== '');
            }
        }
        foreach (['faqs', 'requirements', 'outcomes'] as $field) {
            if (array_key_exists($field, $validated)) {
                // The validator rebuilds rows in rule order, so restore the order the user entered (repeater keys may be UUIDs).
                $entered = array_keys((array) ($data[$field] ?? []));
                $ordered = [];
                foreach ($entered as $key) {
                    if (isset($validated[$field][$key])) {
                        $ordered[] = $validated[$field][$key];
                    }
                }
                $attributes[$field] = $this->cleanRows($ordered);
            }
        }

        return ['attributes' => $attributes, 'slugs' => Arr::only($validated, ['slug_ar', 'slug_en'])];
    }

    /**
     * Drops rows with no text in either language and keeps the entered order.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function cleanRows(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $hasText = false;
            foreach ($row as $key => $value) {
                $row[$key] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $value), static fn ($v) => $v !== null && $v !== '');
                $hasText = $hasText || $row[$key] !== [];
            }
            if ($hasText) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    private function uniqueSlug(string $column, Course $course, ?string $source, string $locale): string
    {
        $base = $this->slugify($source ?? '') ?: ($locale === 'ar' ? 'دورة' : 'course');
        $slug = $base;
        for ($i = 2; Course::query()->where($column, $slug)->when($course->exists, static fn ($q) => $q->whereKeyNot($course->getKey()))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return mb_substr($slug, 0, 120);
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);

        return trim((string) $text, '-');
    }

    /** @param array<string, mixed>|null $before */
    private function auditChange(User $actor, Course $course, bool $created, ?array $before): void
    {
        $status = $course->status->value;
        $after = ['owner_type' => $course->owner_type, 'owner_instructor_id' => $course->owner_instructor_id, 'status' => $status, 'featured' => (bool) $course->featured];

        if ($created) {
            $recorded = $this->audit->record('course.draft_created', $course, (int) $actor->getKey(), null, $after);
        } elseif ($before !== null && (($before['owner_type'] ?? null) !== $after['owner_type'] || ($before['owner_instructor_id'] ?? null) !== $after['owner_instructor_id'])) {
            $recorded = $this->audit->record('course.ownership_changed', $course, (int) $actor->getKey(), $before, Arr::only($after, ['owner_type', 'owner_instructor_id']));
        } else {
            return;
        }

        if ($recorded === null) {
            throw new RuntimeException('Unable to record course audit.');
        }
    }
}
