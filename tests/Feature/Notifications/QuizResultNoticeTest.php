<?php

declare(strict_types=1);

// T-P9-05 | quiz.result wiring, matrix event keys, recipient-locale titles, AccessNotice timezone guard | A1

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\GradeEssayAnswer;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\ReleaseQuizResult;
use App\Domain\Assessment\Actions\SaveQuizAnswers;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assessment\Notifications\EssayReviewedNotice;
use App\Domain\Assessment\Notifications\QuizResultNotice;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Assignments\Notifications\SubmissionDecisionNotice;
use App\Domain\Catalog\Events\CourseReviewed;
use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Domain\Certificates\Certificate;
use App\Domain\Certificates\Notifications\CertificateIssuedNotice;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Notifications\Listeners\NotifyInstructorsOfCourseReview;
use App\Domain\Notifications\Listeners\NotifyOfCourseRevision;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\Notifications\AccessNotice;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Domain\Support\Queries\Learner360Record;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SubmissionDecision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Published course with one published quiz item. Adds a single-choice question, plus an essay when requested.
 *
 * @param  array<string, mixed>  $settings
 * @return array{instructor: User, course: Course, quizItem: CurriculumItem, quiz: Quiz, single: Question, essay: ?Question}
 */
function qrnQuizSetup(array $settings = [], bool $withEssay = false): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الاختبار', 'en' => 'Quiz Notice Course'],
        'slug_ar' => 'qrn-ar-'.uniqid(),
        'slug_en' => 'qrn-en-'.uniqid(),
        'status' => 'published',
        'sequence_mode' => Course::SEQUENCE_SEQUENTIAL,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);
    $section->update(['published' => true]);

    $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار وحدة', 'en' => 'Module Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $quizItem->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, [
        'time_limit_minutes' => 10,
        'pass_mark_percent' => 60,
        'max_attempts' => 5,
        'result_visibility' => 'full',
        'randomize' => false,
        'progression' => 'after_submission',
        ...$settings,
    ]);

    $single = app(ManageQuestions::class)->add($instructor, $quiz, [
        'type' => Question::SINGLE,
        'prompt' => ['en' => 'Pick the right one'],
        'points' => 2,
        'options' => [
            ['text' => ['en' => 'Right'], 'is_correct' => true],
            ['text' => ['en' => 'Wrong'], 'is_correct' => false],
        ],
    ]);
    $essay = $withEssay ? app(ManageQuestions::class)->add($instructor, $quiz, [
        'type' => Question::ESSAY,
        'prompt' => ['en' => 'Explain your work'],
        'points' => 5,
    ]) : null;

    app(SetCurriculumStatus::class)($instructor, $course, [$quizItem->id], CurriculumItem::STATUS_PUBLISHED);

    return [
        'instructor' => $instructor,
        'course' => $course,
        'quizItem' => $quizItem->fresh(),
        'quiz' => $quiz->fresh(),
        'single' => $single,
        'essay' => $essay,
    ];
}

function qrnLearner(Course $course, string $locale = 'en'): User
{
    $learner = User::factory()->create(['status' => 'active', 'locale' => $locale, 'email' => 'qrn-'.uniqid().'@example.test'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return $learner;
}

/** Starts an attempt and saves the correct single answer, plus a written essay when given. */
function qrnAnsweredAttempt(User $learner, CurriculumItem $quizItem, Question $single, ?Question $essay = null): QuizAttempt
{
    $attempt = app(StartQuizAttempt::class)($learner, $quizItem);
    $key = collect($attempt->snapshot['questions'])->firstWhere('id', $single->id)['options'];
    $correct = collect($key)->firstWhere('is_correct', true)['key'];

    $answers = [$single->id => ['selected' => [$correct]]];
    if ($essay !== null) {
        $answers[$essay->id] = ['text' => 'My written answer'];
    }
    app(SaveQuizAnswers::class)($learner, $attempt, $answers);

    return $attempt->fresh();
}

it('sends one quiz.result notice when an auto-graded attempt is first submitted, and a repeat submit adds none', function (): void {
    ['course' => $course, 'quizItem' => $quizItem, 'single' => $single] = qrnQuizSetup(['result_visibility' => 'full']);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single);

    Notification::fake();
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    app(SubmitQuizAttempt::class)($learner, $submitted);

    $notices = Notification::sent($learner, QuizResultNotice::class);
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->event())->toBe('quiz.result')
        ->and($notices->sole()->dedupeKey())->toBe('quiz-review:'.$submitted->getKey())
        ->and($notices->sole()->toArray($learner)['url'])->toBe(route('quiz.attempt', [
            'course' => $course->getKey(), 'item' => $quizItem->getKey(), 'attempt' => $submitted->getKey(),
        ]));
})->group('T-P9-05', 'A1');

it('sends the score-only auto-graded result on submit as well', function (): void {
    ['course' => $course, 'quizItem' => $quizItem, 'single' => $single] = qrnQuizSetup(['result_visibility' => 'score_only']);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single);

    Notification::fake();
    app(SubmitQuizAttempt::class)($learner, $attempt);

    Notification::assertSentToTimes($learner, QuizResultNotice::class, 1);
})->group('T-P9-05', 'A1');

it('sends nothing on submit for a hidden auto-graded quiz, and sends quiz.result once on release', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quizItem' => $quizItem, 'single' => $single] = qrnQuizSetup(['result_visibility' => 'hidden']);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single);

    Notification::fake();
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    Notification::assertNothingSentTo($learner);

    app(ReleaseQuizResult::class)($instructor, $submitted->fresh());
    app(ReleaseQuizResult::class)($instructor, $submitted->fresh()); // re-release is idempotent

    $notices = Notification::sent($learner, QuizResultNotice::class);
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->event())->toBe('quiz.result');
})->group('T-P9-05', 'A1');

it('withholds the essay notice until the written review is completed and released on a hidden essay quiz', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quizItem' => $quizItem, 'single' => $single, 'essay' => $essay] = qrnQuizSetup(['result_visibility' => 'hidden'], withEssay: true);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single, $essay);

    Notification::fake();
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    expect($submitted->needs_review)->toBeTrue();
    Notification::assertNothingSentTo($learner);

    app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 3.0, 'Good structure.');

    expect($submitted->fresh()->needs_review)->toBeFalse();
    Notification::assertNotSentTo($learner, EssayReviewedNotice::class);
    Notification::assertNotSentTo($learner, QuizResultNotice::class);
})->group('T-P9-05', 'A1');

it('sends the essay notice once with quiz.result when grading completes on a visible essay quiz, and a regrade adds none', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quizItem' => $quizItem, 'single' => $single, 'essay' => $essay] = qrnQuizSetup(['result_visibility' => 'full'], withEssay: true);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single, $essay);

    Notification::fake();
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    Notification::assertNotSentTo($learner, QuizResultNotice::class);

    app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 3.0, 'Good structure.');
    app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 4.0, 'Regraded.');

    $notices = Notification::sent($learner, EssayReviewedNotice::class);
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->event())->toBe('quiz.result')
        ->and($notices->sole()->dedupeKey())->toBe('quiz-review:'.$submitted->getKey());
})->group('T-P9-05', 'A1');

it('sends the essay notice for a hidden essay quiz on release, with the same per-attempt key', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quizItem' => $quizItem, 'single' => $single, 'essay' => $essay] = qrnQuizSetup(['result_visibility' => 'hidden'], withEssay: true);
    $learner = qrnLearner($course);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single, $essay);

    Notification::fake();
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 3.0);
    app(ReleaseQuizResult::class)($instructor, $submitted->fresh());

    $notices = Notification::sent($learner, EssayReviewedNotice::class);
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->dedupeKey())->toBe('quiz-review:'.$submitted->getKey());
})->group('T-P9-05', 'A1');

it('renders the quiz title in the learner locale in the stored notification', function (string $locale, string $expectedTitle): void {
    Mail::fake();
    ['course' => $course, 'instructor' => $instructor, 'quizItem' => $quizItem, 'single' => $single, 'essay' => $essay] = qrnQuizSetup(['result_visibility' => 'full'], withEssay: true);
    $learner = qrnLearner($course, $locale);
    $attempt = qrnAnsweredAttempt($learner, $quizItem, $single, $essay);
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt);
    app(GradeEssayAnswer::class)($instructor, $submitted->fresh(), $essay->id, 3.0);

    $row = $learner->notifications()->where('type', EssayReviewedNotice::class)->sole();
    expect($row->data['title'])->toContain($expectedTitle);
})->with([
    'arabic learner' => ['ar', 'اختبار وحدة'],
    'english learner' => ['en', 'Module Quiz'],
])->group('T-P9-05', 'A1');

it('declares quiz.result on both quiz notices and matching per-attempt dedupe keys', function (): void {
    $attempt = new QuizAttempt;
    $attempt->forceFill(['id' => 42]);

    $result = new QuizResultNotice($attempt, 1, 1);
    $essay = new EssayReviewedNotice($attempt, 1, 1);

    expect($result->event())->toBe('quiz.result')
        ->and($essay->event())->toBe('quiz.result')
        ->and($result->dedupeKey())->toBe('quiz-review:42')
        ->and($essay->dedupeKey())->toBe($result->dedupeKey())
        ->and(NotificationMatrix::find('quiz.result'))->not->toBeNull();
})->group('T-P9-05', 'A1');

it('keeps the quiz result label in both languages and maps the notice to the quiz_result kind', function (): void {
    expect(trans('review.notice.quiz_result.title', [], 'en'))->not->toBe('review.notice.quiz_result.title')
        ->and(trans('review.notice.quiz_result.title', [], 'ar'))->not->toBe('review.notice.quiz_result.title')
        ->and(Lang::has('support.notice_kinds.quiz_result', 'en'))->toBeTrue()
        ->and(Lang::has('support.notice_kinds.quiz_result', 'ar'))->toBeTrue();

    $method = new ReflectionMethod(Learner360Record::class, 'noticeLabel');
    $method->setAccessible(true);
    $label = $method->invoke(null, 'QuizResultNotice'); // dispatcher stores class_basename

    expect($label)->toBe(__('support.notice_kinds.quiz_result'))
        ->and($label)->not->toBe(__('support.notice_kinds.other'));
})->group('T-P9-05', 'A1');

it('gives each event-backed notice its matrix key', function (): void {
    $decisionEvents = [
        SubmissionDecision::GRADED => 'assignment.graded',
        SubmissionDecision::ACCEPTED => 'assignment.accepted',
        SubmissionDecision::REJECTED => 'assignment.rejected',
        SubmissionDecision::REVISION_REQUESTED => 'assignment.revision',
    ];

    foreach ($decisionEvents as $decision => $event) {
        $model = new SubmissionDecision;
        $model->decision = $decision;

        expect((new SubmissionDecisionNotice($model, 1, 1, 'Title'))->event())->toBe($event)
            ->and(NotificationMatrix::find($event))->not->toBeNull();
    }

    expect((new CertificateIssuedNotice(new Certificate))->event())->toBe('certificate.issued')
        ->and(NotificationMatrix::find('certificate.issued'))->not->toBeNull();
})->group('T-P9-05', 'A1');

it('documents every NotificationMatrix event in docs/NOTIFICATION_MATRIX.md, including course review events', function (): void {
    $doc = (string) file_get_contents(base_path('docs/NOTIFICATION_MATRIX.md'));

    foreach (array_keys(NotificationMatrix::all()) as $key) {
        expect($doc)->toContain("| `{$key}` |");
    }

    expect($doc)->toContain('| `course.approved` |')
        ->and($doc)->toContain('| `course.rejected` |');
})->group('T-P9-05', 'A1');

it('does not throw on an invalid recipient timezone and falls back to the app timezone', function (): void {
    app()->setLocale('en');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Access Course'],
        'slug_ar' => 'acc-ar-'.uniqid(),
        'slug_en' => 'acc-en-'.uniqid(),
        'status' => 'published',
    ]);
    $endsAt = CarbonImmutable::parse('2027-04-10 23:30:00', 'UTC');
    $recipient = User::factory()->create(['timezone' => 'Invalid/Zone']);

    $notice = new AccessNotice('access.expiring', $course, 'suffix-1', $endsAt);
    $message = $notice->toArray($recipient)['message'];

    // app.timezone is UTC, so the end date stays on April 10.
    expect($message)->toContain('April 10, 2027');
})->group('T-P9-05', 'A1');

it('formats the access end date in a valid recipient timezone', function (): void {
    app()->setLocale('en');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Access Course'],
        'slug_ar' => 'acc2-ar-'.uniqid(),
        'slug_en' => 'acc2-en-'.uniqid(),
        'status' => 'published',
    ]);
    $endsAt = CarbonImmutable::parse('2027-04-10 23:30:00', 'UTC');
    $recipient = User::factory()->create(['timezone' => 'Africa/Cairo']);

    $message = (new AccessNotice('access.expiring', $course, 'suffix-2', $endsAt))->toArray($recipient)['message'];

    // Cairo is UTC+3 in April, so 23:30 UTC is already April 11.
    expect($message)->toContain('April 11, 2027');
})->group('T-P9-05', 'A1');

it('sends instructors the course title in each instructor locale', function (): void {
    Notification::fake();
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الاختبار', 'en' => 'Quiz Review Course'],
        'slug_ar' => 'rev-ar-'.uniqid(),
        'slug_en' => 'rev-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->forceFill(['reviewed_at' => now()])->save();
    $arInstructor = User::factory()->create(['status' => 'active', 'locale' => 'ar']);
    $enInstructor = User::factory()->create(['status' => 'active', 'locale' => 'en']);
    $course->instructors()->attach($arInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $course->instructors()->attach($enInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    app(NotifyInstructorsOfCourseReview::class)->handle(new CourseReviewed($course->fresh(), true, null));

    $arText = collect(Notification::sent($arInstructor, TemplatedNotice::class)->sole()->toArray($arInstructor))->implode(' ');
    $enText = collect(Notification::sent($enInstructor, TemplatedNotice::class)->sole()->toArray($enInstructor))->implode(' ');

    expect($arText)->toContain('دورة الاختبار')
        ->and($enText)->toContain('Quiz Review Course')
        ->and($enText)->not->toContain('دورة الاختبار');
})->group('T-P9-05', 'A1');

it('sends reviewers and instructors a revision outcome in each recipient locale', function (): void {
    Notification::fake();
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة المراجعة', 'en' => 'Revision Course'],
        'slug_ar' => 'revn-ar-'.uniqid(),
        'slug_en' => 'revn-en-'.uniqid(),
        'status' => 'published',
    ]);
    $arInstructor = User::factory()->create(['status' => 'active', 'locale' => 'ar']);
    $course->instructors()->attach($arInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $revision = new CourseRevision(['number' => 2]);
    $revision->setRelation('course', $course);

    app(NotifyOfCourseRevision::class)->reviewed(new CourseRevisionReviewed($revision, true, null));

    $text = collect(Notification::sent($arInstructor, TemplatedNotice::class)->sole()->toArray($arInstructor))->implode(' ');
    expect($text)->toContain('دورة المراجعة');
})->group('T-P9-05', 'A1');

it('localizes the assignment title per learner for a decided submission', function (string $locale, string $expected): void {
    Mail::fake();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الواجب', 'en' => 'Assignment Course'],
        'slug_ar' => 'asg-ar-'.uniqid(),
        'slug_en' => 'asg-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);
    $section->update(['published' => true]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'لوحة الطبيعة', 'en' => 'Nature Study']]);
    $item->itemable->update(['allowed_types' => ['text']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);

    $learner = qrnLearner($course, $locale);
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Work');
    $submission = app(SubmitAssignment::class)($learner, $item->fresh());
    app(DecideSubmission::class)($instructor, $submission->fresh(), SubmissionDecision::ACCEPTED);

    $row = $learner->notifications()->where('type', SubmissionDecisionNotice::class)->sole();
    expect($row->data['title'])->toContain($expected);
})->with([
    'arabic learner' => ['ar', 'لوحة الطبيعة'],
    'english learner' => ['en', 'Nature Study'],
])->group('T-P9-05', 'A1');
