<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Assessment\Actions\SaveQuizAnswers;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assessment\Queries\QuizAttemptView;
use App\Domain\Assessment\QuizRefusedException;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The learner side of a quiz attempt. A learner without access, or an attempt of someone else, gets a 404. Every rule
 * (time, attempts, grading, visibility) lives in the Actions; the browser only shows state and sends answers.
 */
final class QuizAttemptController
{
    public function __construct(
        private readonly ActingLearner $acting,
        private readonly EntitlementService $access,
        private readonly StartQuizAttempt $start,
        private readonly SaveQuizAnswers $save,
        private readonly SubmitQuizAttempt $submit,
    ) {}

    public function start(Request $request, Course $course, CurriculumItem $item): RedirectResponse
    {
        $learner = $this->learner();
        $this->guardItem($course, $item);

        try {
            $attempt = $this->start->__invoke($learner, $item);
        } catch (QuizRefusedException $exception) {
            abort_if($exception->reason === QuizRefusedException::NO_ACCESS, 404);

            return redirect()->route('learn.show', ['course' => $course->getKey(), 'item' => $item->getKey()])->with('quiz_error', $exception->getMessage());
        }

        return redirect()->route('quiz.attempt', ['course' => $course->getKey(), 'item' => $item->getKey(), 'attempt' => $attempt->getKey()]);
    }

    public function show(Request $request, Course $course, CurriculumItem $item, QuizAttempt $attempt): View
    {
        $learner = $this->learner();
        $quiz = $this->guardAttempt($course, $item, $attempt, $learner);

        $expired = $attempt->isOpen() && $attempt->deadline_at !== null
            && now()->gt($attempt->deadline_at->copy()->addSeconds((int) config('assessment.grace_seconds')));
        $open = $attempt->isOpen() && ! $expired;

        return view('learn.quiz-attempt', [
            'course' => $course, 'item' => $item, 'quiz' => $quiz, 'attempt' => $attempt, 'learner' => $learner, 'open' => $open, 'expired' => $expired,
            'questions' => $open ? QuizAttemptView::questions($attempt) : [],
            'result' => $attempt->isOpen() ? null : QuizAttemptView::result($attempt),
            'remaining' => $open && $attempt->deadline_at !== null ? max(0, (int) now()->diffInSeconds($attempt->deadline_at, false)) : null,
        ]);
    }

    public function save(Request $request, Course $course, CurriculumItem $item, QuizAttempt $attempt): JsonResponse
    {
        $learner = $this->learner();
        $this->guardAttempt($course, $item, $attempt, $learner);

        try {
            $saved = $this->save->__invoke($learner, $attempt, (array) $request->input('answers', []));
        } catch (QuizRefusedException $exception) {
            abort_if($exception->reason === QuizRefusedException::NO_ACCESS, 404);

            return response()->json(['saved' => false, 'reason' => $exception->reason, 'message' => $exception->getMessage(), 'closed' => in_array($exception->reason, [QuizRefusedException::EXPIRED, QuizRefusedException::NOT_OPEN], true)], 409);
        }

        $fresh = $attempt->refresh();

        return response()->json([
            'saved' => true, 'count' => $saved,
            'remaining' => $fresh->deadline_at === null ? null : max(0, (int) now()->diffInSeconds($fresh->deadline_at, false)),
        ]);
    }

    public function submit(Request $request, Course $course, CurriculumItem $item, QuizAttempt $attempt): RedirectResponse
    {
        $learner = $this->learner();
        $this->guardAttempt($course, $item, $attempt, $learner);

        try {
            $done = $this->submit->__invoke($learner, $attempt, (array) $request->input('answers', []));
        } catch (QuizRefusedException $exception) {
            abort_if($exception->reason === QuizRefusedException::NO_ACCESS, 404);

            return redirect()->route('quiz.attempt', ['course' => $course->getKey(), 'item' => $item->getKey(), 'attempt' => $attempt->getKey()])->with('quiz_error', $exception->getMessage());
        }

        return redirect()->route('quiz.attempt', ['course' => $course->getKey(), 'item' => $item->getKey(), 'attempt' => $done->getKey()])
            ->with('quiz_notice', $done->submitted_by === 'auto' ? __('quiz.attempt.time_up') : __('quiz.attempt.submitted'));
    }

    private function guardItem(Course $course, CurriculumItem $item): void
    {
        abort_unless((int) $item->course_id === (int) $course->getKey() && $item->itemable instanceof Quiz, 404);
    }

    private function guardAttempt(Course $course, CurriculumItem $item, QuizAttempt $attempt, User $learner): Quiz
    {
        $this->guardItem($course, $item);
        $quiz = $item->itemable;
        abort_unless($quiz instanceof Quiz && (int) $attempt->quiz_id === (int) $quiz->getKey() && (int) $attempt->learner_id === (int) $learner->getKey(), 404);
        // A learner whose access has ended can still read finished attempts but must not continue an open one.
        abort_if($attempt->isOpen() && ! $this->access->canAccessItem($learner, $item)->allowed, 404);

        return $quiz;
    }

    private function learner(): User
    {
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        return $learner;
    }
}
