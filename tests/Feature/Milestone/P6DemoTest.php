<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\GradeEssayAnswer;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\ReleaseQuizResult;
use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Certificates\Actions\RevokeCertificate;
use App\Domain\Certificates\Certificate;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SubmissionDecision;
use App\Models\SubmissionFile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p6As($test, User $user)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

/**
 * T-P6-MG demo script, executed over HTTP for the learner side and through the domain Actions for staff:
 * instructor builds a quiz (auto-graded and essay questions) and an assignment → learner enrolls and attempts the quiz
 * → the essay is graded and the result released → the learner completes the text lesson and submits a late
 * assignment file → the instructor accepts it → the completion engine marks the course complete → a certificate is
 * issued and its public /verify page is valid → revocation makes the page and the download disappear.
 */
it('runs the P6 demo script end to end', function (): void {
    // A private, unique disk root for this run; removed in the finally block below.
    $diskName = 'p6demo-'.uniqid();
    $diskRoot = storage_path('framework/testing/disks/'.$diskName);
    config(["filesystems.disks.{$diskName}" => ['driver' => 'local', 'root' => $diskRoot, 'throw' => false]]);
    config(['media.disk' => $diskName]);

    try {
        Notification::fake();
        Pdf::fake();

        // Accounts: an instructor who owns the course, an admin-free learner and a stranger who must stay out.
        $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
        $learner = User::factory()->create([
            'status' => 'active', 'name' => 'Layla Demo', 'email' => 'p6.learner@example.test', 'phone' => '+201000000666',
            'certificate_name_ar' => 'ليلى تجريبي', 'certificate_name_en' => 'Layla Demo',
        ])->assignRole('student');
        $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');

        // 1. Instructor builds a free, published course with a section.
        $course = Course::query()->create([
            'title' => ['en' => 'P6 Demo Course', 'ar' => 'دورة تجريبية'], 'slug_ar' => 'p6-ar-'.uniqid(), 'slug_en' => 'p6-en-'.uniqid(),
            'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
        ]);
        $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
        $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
        $section->update(['published' => true]);

        // 2. Instructor builds the quiz: one question of each auto-graded type plus an essay, 15 points in total.
        $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Module Quiz', 'ar' => 'اختبار']]);
        /** @var Quiz $quiz */
        $quiz = $quizItem->itemable;
        app(ConfigureQuiz::class)($instructor, $quiz, [
            'time_limit_minutes' => 10, 'pass_mark_percent' => 60, 'max_attempts' => 3,
            'result_visibility' => 'hidden', 'randomize' => false, 'progression' => 'after_submission',
        ]);
        $manage = app(ManageQuestions::class);

        // Validation per type: a single-choice question must have exactly one correct option.
        expect(fn () => $manage->add($instructor, $quiz, [
            'type' => Question::SINGLE, 'prompt' => ['en' => 'Two correct?'], 'points' => 2,
            'options' => [['text' => ['en' => 'A'], 'is_correct' => true], ['text' => ['en' => 'B'], 'is_correct' => true]],
        ]))->toThrow(ValidationException::class);

        $single = $manage->add($instructor, $quiz, [
            'type' => Question::SINGLE, 'prompt' => ['en' => 'Pick the primary colour'], 'explanation' => ['en' => 'EXPLANATION_SHOWN_SINGLE'], 'points' => 2,
            'options' => [['text' => ['en' => 'Red'], 'is_correct' => true], ['text' => ['en' => 'Green'], 'is_correct' => false]],
        ]);
        $multiple = $manage->add($instructor, $quiz, [
            'type' => Question::MULTIPLE, 'prompt' => ['en' => 'Pick the warm colours'], 'points' => 4,
            'options' => [['text' => ['en' => 'Orange'], 'is_correct' => true], ['text' => ['en' => 'Yellow'], 'is_correct' => true], ['text' => ['en' => 'Blue'], 'is_correct' => false]],
        ]);
        $tf = $manage->add($instructor, $quiz, ['type' => Question::TRUE_FALSE, 'prompt' => ['en' => 'Water is wet'], 'points' => 2, 'true_is_correct' => true]);
        $short = $manage->add($instructor, $quiz, [
            'type' => Question::SHORT_ANSWER, 'prompt' => ['en' => 'Name the medium'], 'points' => 2,
            'options' => [['text' => ['en' => 'Watercolor']]],
        ]);
        $essay = $manage->add($instructor, $quiz, ['type' => Question::ESSAY, 'prompt' => ['en' => 'Describe your palette'], 'points' => 5]);

        // 3. Instructor builds an assignment: image or text, 2 MB max, late work accepted with a 15 % penalty.
        $assignItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Studio Sketch', 'ar' => 'رسم']]);
        /** @var Assignment $assignment */
        $assignment = $assignItem->itemable;
        app(ConfigureAssignment::class)($instructor, $assignment, [
            'instructions' => ['en' => 'Sketch a still life.'], 'allowed_types' => ['text', 'image'], 'max_size_kb' => 2048,
            'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'timezone' => 'Africa/Cairo',
            'late_policy' => Assignment::LATE_PENALTY, 'late_penalty_percent' => 15, 'max_revisions' => 1,
        ]);

        // A required text lesson, the quiz and the assignment are published together.
        $lesson = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['en' => 'Reading', 'ar' => 'قراءة']]);
        app(SetCurriculumStatus::class)($instructor, $course, [$quizItem->id, $assignItem->id, $lesson->id], CurriculumItem::STATUS_PUBLISHED);

        // Completion rules: every lesson, the quiz passed, the assignment accepted, no manual approval.
        app(SaveCompletionRules::class)($instructor, $course, [
            'lesson_percent' => 100, 'require_quizzes' => true, 'require_assignments' => true, 'manual_approval' => false,
        ]);

        // 4. Learner enrolls over HTTP from the catalog CTA; a stranger without access gets a 404 on the quiz.
        p6As($this, $learner)->post(route('catalog.enroll', ['course' => $course->id]))
            ->assertRedirect(route('learn.course', ['course' => $course->id]));
        p6As($this, $stranger)->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]))->assertNotFound();

        // 5. Learner starts the quiz and saves and submits answers over HTTP; the essay leaves the attempt awaiting review.
        p6As($this, $learner)->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]))->assertRedirect();
        $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('learner_id', $learner->id)->firstOrFail();
        $options = collect($attempt->snapshot['questions'])->keyBy('id');
        $singleKey = collect($options[$single->id]['options'])->firstWhere('is_correct', true)['key'];
        $multiKeys = collect($options[$multiple->id]['options'])->where('is_correct', true)->pluck('key')->values()->all();
        $tfKey = collect($options[$tf->id]['options'])->first()['key'];
        $answers = [
            $single->id => ['selected' => [$singleKey]],
            $multiple->id => ['selected' => $multiKeys],
            $tf->id => ['selected' => [$tfKey]],
            $short->id => ['text' => '  watercolor  '],
            $essay->id => ['text' => 'A warm palette with a cool accent.'],
        ];
        p6As($this, $learner)->postJson(route('quiz.save', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt->id]), ['answers' => $answers])
            ->assertOk()->assertJsonPath('saved', true);
        p6As($this, $learner)->post(route('quiz.submit', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt->id]), ['answers' => $answers])
            ->assertRedirect();

        $submitted = $attempt->fresh();
        expect((float) $submitted->score)->toBe(10.0)
            ->and((float) $submitted->total)->toBe(15.0)
            ->and($submitted->needs_review)->toBeTrue()
            ->and($submitted->passed)->toBeNull();
        p6As($this, $learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt->id]))
            ->assertOk()->assertSee('data-result-hidden', false)->assertDontSee('EXPLANATION_SHOWN_SINGLE');

        // 6. Instructor grades the essay (3 of 5) and releases the result; the learner now sees 13 / 15 and the explanation.
        app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 3.0, 'Nice palette.');
        app(ReleaseQuizResult::class)($instructor, $submitted->fresh());
        p6As($this, $learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt->id]))
            ->assertOk()->assertSee('data-score', false)->assertSee('13 / 15')->assertSee('EXPLANATION_SHOWN_SINGLE');
        expect($attempt->fresh()->passed)->toBeTrue();

        // 7. Learner completes the required text lesson over HTTP.
        p6As($this, $learner)->postJson(route('learn.complete', ['course' => $course->id, 'item' => $lesson->id]))
            ->assertOk()->assertJsonPath('status', 'completed');

        // 8. Assignment: the due date has passed, so the file submission is late; the late policy applies a 15 % penalty.
        $assignment->fresh()->forceFill(['due_at' => now()->subHour()])->save();
        $sketch = UploadedFile::fake()->image('still-life.png', 150, 150);
        p6As($this, $learner)->post(route('assignment.submit', ['course' => $course->id, 'item' => $assignItem->id]), [
            'text' => 'My still life sketch.',
            'files' => [$sketch],
        ])->assertRedirect();

        $submission = AssignmentSubmission::query()->where('learner_id', $learner->id)->firstOrFail();
        $revision = $submission->revisions()->where('number', 1)->firstOrFail();
        expect(SubmissionFile::query()->where('revision_id', $revision->id)->count())->toBe(1)
            ->and($submission->status)->toBe(AssignmentSubmission::SUBMITTED)
            ->and($revision->is_late)->toBeTrue()
            ->and($revision->late_penalty_percent)->toBe(15);

        // The learner's own certificate page stays empty until the course is actually complete.
        expect(Certificate::query()->count())->toBe(0);

        // 9. Instructor accepts the submission; the completion engine evaluates the rules and marks the course complete.
        app(DecideSubmission::class)($instructor, $submission->fresh(), SubmissionDecision::ACCEPTED, null, 'Approved.');

        $completion = CourseCompletion::query()->where('course_id', $course->id)->where('learner_id', $learner->id)->firstOrFail();
        expect($completion->source)->toBe(CourseCompletion::AUTO);

        // 10. A certificate is issued once for that completion, and its public verification page works.
        $certificate = Certificate::query()->where('course_completion_id', $completion->id)->sole();
        expect($certificate->isValid())->toBeTrue()
            ->and($certificate->number)->toMatch('/^YG-\d{4}-[A-HJ-NP-Z2-9]{8}$/');
        $this->get(route('certificates.verify', ['token' => $certificate->token]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('data-verify-status="valid"', false)
            ->assertSee($certificate->number)
            ->assertDontSee('p6.learner@example.test')
            ->assertDontSee('+201000000666');

        // The learner can download the PDF (rendering is faked on this host).
        p6As($this, $learner)->get(route('certificates.download', ['certificate' => $certificate->id, 'lang' => 'en']))->assertOk();

        // 11. Revocation: staff revoke the certificate; the verify page turns to revoked and the download is gone.
        app(RevokeCertificate::class)($instructor, $certificate, 'Issued in the P6 demo');
        $this->get(route('certificates.verify', ['token' => $certificate->token]))
            ->assertOk()
            ->assertSee('data-verify-status="revoked"', false)
            ->assertDontSee('Issued in the P6 demo');
        p6As($this, $learner)->get(route('certificates.download', ['certificate' => $certificate->id, 'lang' => 'en']))->assertNotFound();
        expect($certificate->fresh()->isValid())->toBeFalse();
    } finally {
        File::deleteDirectory($diskRoot);
    }
})->group('T-P6-MG', 'A1');
