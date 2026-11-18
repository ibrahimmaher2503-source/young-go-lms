<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\RevisionContent;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Saves the proposed content of a draft revision. Nothing live changes. */
final class SaveCourseRevision
{
    /** @param array<string, mixed> $input */
    public function __invoke(User $actor, CourseRevision $revision, array $input): CourseRevision
    {
        Gate::forUser($actor)->authorize('update', $revision);

        return DB::transaction(function () use ($revision, $input): CourseRevision {
            $locked = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
            if ($locked->status !== CourseRevisionStatus::Draft) {
                throw ValidationException::withMessages(['revision' => __('course_revisions.not_draft')]);
            }

            $locked->forceFill(['payload' => RevisionContent::normalize($input, $locked->base)])->save();

            return $locked;
        });
    }
}
