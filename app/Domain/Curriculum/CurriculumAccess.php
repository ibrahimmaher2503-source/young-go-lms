<?php

declare(strict_types=1);

namespace App\Domain\Curriculum;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Shared authorization and input rules for the curriculum Actions. */
final class CurriculumAccess
{
    public const string UPDATE = 'curriculum.update';

    public const string CREATE = 'curriculum.create';

    public const string DELETE = 'curriculum.delete';

    /** The actor must manage this course (assigned with manage scope, or admin) and hold the permission. */
    public static function authorize(User $actor, Course $course, string $permission = self::UPDATE): void
    {
        Gate::forUser($actor)->authorize('update', $course);

        if (! $actor->can($permission)) {
            throw new AuthorizationException;
        }
    }

    public static function authorizeLesson(User $actor, Lesson $lesson, string $permission): void
    {
        $item = $lesson->item()->with('course')->firstOrFail();
        self::authorize($actor, $item->course, $permission);
    }

    /**
     * Title must have text in at least one language.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function validTitle(array $data, string $field = 'title'): array
    {
        $validated = Validator::make($data, [
            $field => ['required', 'array'],
            "{$field}.ar" => ['nullable', 'string', 'max:150'],
            "{$field}.en" => ['nullable', 'string', 'max:150'],
        ])->validate();

        $title = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $validated[$field]), static fn (string $v): bool => $v !== '');
        if ($title === []) {
            throw ValidationException::withMessages(["{$field}.ar" => __('validation.required', ['attribute' => __('curriculum.title')])]);
        }

        return $title;
    }
}
