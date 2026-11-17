<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\ActingLearner;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Learner progress events from the player. The server decides what they mean; a learner without access sees 404. */
final class ProgressController
{
    public function __construct(private readonly ActingLearner $acting, private readonly RecordProgress $record) {}

    public function heartbeat(Request $request, Course $course, CurriculumItem $item): JsonResponse
    {
        $position = (int) $request->validate(['position' => ['required', 'integer', 'between:0,86400']])['position'];

        return $this->respond($course, $item, fn (User $learner): LessonProgress => $this->record->heartbeat($learner, $item, $position));
    }

    public function complete(Request $request, Course $course, CurriculumItem $item): JsonResponse
    {
        return $this->respond($course, $item, fn (User $learner): LessonProgress => $this->record->markComplete($learner, $item));
    }

    /** @param  callable(User): LessonProgress  $work */
    private function respond(Course $course, CurriculumItem $item, callable $work): JsonResponse
    {
        abort_unless((int) $item->course_id === (int) $course->getKey(), 404);
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        try {
            $progress = $work($learner);
        } catch (AuthorizationException) {
            abort(404);
        } catch (ValidationException $exception) {
            return response()->json(['message' => (string) collect($exception->errors())->flatten()->first()], 422);
        }

        $course = CourseProgress::query()->where('learner_id', $learner->getKey())->where('course_id', $item->course_id)->first();

        return response()->json([
            'status' => $progress->status, 'percent' => $progress->percent, 'position' => $progress->position_seconds,
            'course_percent' => $course->percent ?? 0, 'course_completed' => $course?->completed_at !== null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
