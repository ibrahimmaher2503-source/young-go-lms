<?php

// T-P6-01 | PRD §12, Checklist §8 | A1 A2

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Queries\QuizGuardQuestions;
use App\Domain\Assessment\QuestionRules;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Filament\Resources\Courses\Pages\ManageQuiz;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** @return array{course: Course, instructor: User, quiz: Quiz, section: CourseSection, quizItem: CurriculumItem} */
function qatCourseQuiz(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Quiz Authoring', 'ar' => 'إعداد الاختبار'],
        'slug_ar' => 'qat-ar-'.uniqid(),
        'slug_en' => 'qat-en-'.uniqid(),
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1', 'ar' => 'القسم 1']]);
    $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Unit Quiz', 'ar' => 'اختبار الوحدة']]);

    /** @var Quiz $quiz */
    $quiz = $quizItem->itemable;

    return compact('course', 'instructor', 'quiz', 'section', 'quizItem');
}

function qatValidationErrorKey(callable $callback): ?string
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return array_key_first($exception->errors());
    }

    return null;
}

it('validates question rules per type, option counts, points bounds, and prompt rules', function (array $payload, ?string $expectedErrorKey, ?string $expectedLangKey): void {
    if ($expectedErrorKey === null) {
        $cleaned = QuestionRules::clean($payload);
        expect($cleaned['type'])->toBe($payload['type'])
            ->and($cleaned['points'])->toBeGreaterThanOrEqual(0.25);

        if ($payload['type'] === Question::ESSAY) {
            expect($cleaned['options'])->toBe([]);
        }
        if ($payload['type'] === Question::TRUE_FALSE) {
            expect($cleaned['options'])->toHaveCount(2);
            expect($cleaned['options'][0]['is_correct'])->toBe((bool) $payload['true_is_correct']);
            expect($cleaned['options'][1]['is_correct'])->toBe(! (bool) $payload['true_is_correct']);
        }
    } else {
        $caught = null;
        try {
            QuestionRules::clean($payload);
        } catch (ValidationException $exception) {
            $caught = $exception;
        }
        expect($caught)->toBeInstanceOf(ValidationException::class)
            ->and($caught->errors())->toHaveKey($expectedErrorKey);
        if ($expectedLangKey !== null) {
            foreach (['ar', 'en'] as $locale) {
                app()->setLocale($locale);
                try {
                    QuestionRules::clean($payload);
                } catch (ValidationException $e) {
                    expect($e->errors()[$expectedErrorKey][0])->toBe(__($expectedLangKey, ['min' => 2, 'max' => 8, 'accepted' => 10], $locale));
                }
            }
            app()->setLocale('en');
        }
    }
})->with([
    'valid single' => [
        [
            'type' => 'single',
            'prompt' => ['ar' => 'سؤال؟ 🎨', 'en' => 'Question? 🎨'],
            'points' => 1.5,
            'options' => [
                ['text' => ['en' => 'A'], 'is_correct' => true],
                ['text' => ['en' => 'B'], 'is_correct' => false],
                ['text' => ['en' => '   '], 'is_correct' => false],
            ],
        ],
        null,
        null,
    ],
    'single 0 correct' => [
        ['type' => 'single', 'prompt' => ['en' => 'Q'], 'points' => 1, 'options' => [['text' => ['en' => 'A'], 'is_correct' => false], ['text' => ['en' => 'B'], 'is_correct' => false]]],
        'options',
        'quiz.errors.single_one_correct',
    ],
    'single 2 correct' => [
        ['type' => 'single', 'prompt' => ['en' => 'Q'], 'points' => 1, 'options' => [['text' => ['en' => 'A'], 'is_correct' => true], ['text' => ['en' => 'B'], 'is_correct' => true]]],
        'options',
        'quiz.errors.single_one_correct',
    ],
    'single 1 option' => [
        ['type' => 'single', 'prompt' => ['en' => 'Q'], 'points' => 1, 'options' => [['text' => ['en' => 'A'], 'is_correct' => true]]],
        'options',
        'quiz.errors.options_range',
    ],
    'single 9 options' => [
        ['type' => 'single', 'prompt' => ['en' => 'Q'], 'points' => 1, 'options' => array_map(fn ($i) => ['text' => ['en' => 'O'.$i], 'is_correct' => $i === 1], range(1, 9))],
        'options',
        'quiz.errors.options_range',
    ],
    'valid multiple' => [
        ['type' => 'multiple', 'prompt' => ['en' => 'Pick'], 'points' => 2, 'options' => [['text' => ['en' => 'A'], 'is_correct' => true], ['text' => ['en' => 'B'], 'is_correct' => false]]],
        null,
        null,
    ],
    'multiple all correct' => [
        ['type' => 'multiple', 'prompt' => ['en' => 'Pick'], 'points' => 1, 'options' => [['text' => ['en' => 'A'], 'is_correct' => true], ['text' => ['en' => 'B'], 'is_correct' => true]]],
        'options',
        'quiz.errors.multiple_needs_wrong',
    ],
    'multiple none correct' => [
        ['type' => 'multiple', 'prompt' => ['en' => 'Pick'], 'points' => 1, 'options' => [['text' => ['en' => 'A'], 'is_correct' => false], ['text' => ['en' => 'B'], 'is_correct' => false]]],
        'options',
        'quiz.errors.multiple_needs_correct',
    ],
    'valid true_false true' => [
        ['type' => 'true_false', 'prompt' => ['ar' => 'صح أم خطأ'], 'points' => 0.25, 'true_is_correct' => true],
        null,
        null,
    ],
    'valid true_false false' => [
        ['type' => 'true_false', 'prompt' => ['en' => 'True or false'], 'points' => 1000, 'true_is_correct' => false],
        null,
        null,
    ],
    'true_false null answer' => [
        ['type' => 'true_false', 'prompt' => ['en' => 'T/F'], 'points' => 1, 'true_is_correct' => null],
        'true_is_correct',
        'quiz.errors.true_false_needs_answer',
    ],
    'valid short_answer' => [
        ['type' => 'short_answer', 'prompt' => ['en' => 'Color'], 'points' => 1, 'options' => [['text' => ['en' => 'Red'], 'is_correct' => true]]],
        null,
        null,
    ],
    'short_answer 0 accepted' => [
        ['type' => 'short_answer', 'prompt' => ['en' => 'Color'], 'points' => 1, 'options' => []],
        'options',
        'quiz.errors.accepted_range',
    ],
    'short_answer 11 accepted' => [
        ['type' => 'short_answer', 'prompt' => ['en' => 'Color'], 'points' => 1, 'options' => array_map(fn ($i) => ['text' => ['en' => 'A'.$i], 'is_correct' => true], range(1, 11))],
        'options',
        'quiz.errors.accepted_range',
    ],
    'valid essay ignores sent options' => [
        ['type' => 'essay', 'prompt' => ['en' => 'Describe lighting'], 'points' => 5, 'options' => [['text' => ['en' => 'Ignored'], 'is_correct' => true]]],
        null,
        null,
    ],
    'points below min 0.24' => [
        ['type' => 'essay', 'prompt' => ['en' => 'Q'], 'points' => 0.24],
        'points',
        null,
    ],
    'points above max 1000.01' => [
        ['type' => 'essay', 'prompt' => ['en' => 'Q'], 'points' => 1000.01],
        'points',
        null,
    ],
    'whitespace prompt' => [
        ['type' => 'essay', 'prompt' => ['ar' => '   ', 'en' => "\t\n"], 'points' => 1],
        'prompt',
        'quiz.errors.prompt_required',
    ],
    'prompt over 5000 chars' => [
        ['type' => 'essay', 'prompt' => ['en' => str_repeat('a', 5001)], 'points' => 1],
        'prompt.en',
        null,
    ],
    'unknown type' => [
        ['type' => 'matrix', 'prompt' => ['en' => 'Q'], 'points' => 1],
        'type',
        null,
    ],
])->group('T-P6-01', 'A1');

it('manages question lifecycle: version increments on change only, soft deactivation, and scoped reordering', function (): void {
    ['instructor' => $instructor, 'quiz' => $quiz] = qatCourseQuiz();
    ['instructor' => $otherInstructor, 'quiz' => $otherQuiz] = qatCourseQuiz();
    $manage = app(ManageQuestions::class);

    $q1 = $manage->add($instructor, $quiz, [
        'type' => 'single',
        'prompt' => ['en' => 'First question'],
        'points' => 2.25,
        'options' => [
            ['text' => ['en' => 'Yes'], 'is_correct' => true],
            ['text' => ['en' => 'No'], 'is_correct' => false],
        ],
    ]);
    $q2 = $manage->add($instructor, $quiz, [
        'type' => 'essay',
        'prompt' => ['en' => 'Second question'],
        'points' => 3.5,
    ]);
    $otherQ = $manage->add($otherInstructor, $otherQuiz, [
        'type' => 'essay',
        'prompt' => ['en' => 'Foreign question'],
        'points' => 10,
    ]);

    expect($q1->sort)->toBe(1)->and($q1->version)->toBe(1)->and($q1->active)->toBeTrue()
        ->and($q2->sort)->toBe(2)
        ->and($quiz->totalMarks())->toBe(5.75)
        ->and(Activity::query()->where('event', 'quiz.question_added')->count())->toBe(3);

    // Identical update keeps version 1 and emits no audit
    $unchanged = $manage->update($instructor, $q1, [
        'type' => 'single',
        'prompt' => ['en' => 'First question'],
        'points' => 2.25,
        'options' => [
            ['text' => ['en' => 'Yes'], 'is_correct' => true],
            ['text' => ['en' => 'No'], 'is_correct' => false],
        ],
    ]);
    expect($unchanged->version)->toBe(1)
        ->and(Activity::query()->where('event', 'quiz.question_changed')->count())->toBe(0);

    // Real change to essay clears options, increments version, audits before/after
    $changed = $manage->update($instructor, $q1, [
        'type' => 'essay',
        'prompt' => ['en' => 'Updated prompt'],
        'points' => 4,
    ]);
    expect($changed->version)->toBe(2)
        ->and($changed->options)->toBeEmpty()
        ->and($quiz->totalMarks())->toBe(7.5);
    $changeAudit = Activity::query()->where('event', 'quiz.question_changed')->sole();
    expect($changeAudit->properties['before']['type'])->toBe('single')
        ->and($changeAudit->properties['after']['type'])->toBe('essay');

    // Soft deactivation excludes from activeQuestions and totalMarks, restore brings it back
    $manage->setActive($instructor, $q2, false);
    expect($quiz->activeQuestions()->count())->toBe(1)
        ->and($quiz->totalMarks())->toBe(4.0);
    $manage->setActive($instructor, $q2->refresh(), true);
    expect($quiz->activeQuestions()->count())->toBe(2)
        ->and($quiz->totalMarks())->toBe(7.5);

    // Reordering inside quiz leaves other quiz untouched
    $manage->move($instructor, $q2->refresh(), 'up');
    expect($quiz->questions()->pluck('id')->all())->toBe([$q2->id, $q1->id])
        ->and($otherQ->refresh()->sort)->toBe(1);

    expect(qatValidationErrorKey(fn () => $manage->move($instructor, $q2, 'sideways')))->toBe('direction');
})->group('T-P6-01', 'A1');

it('configures quiz settings with pass-mark guard and exposes only open prompts to QuizGuardQuestions', function (): void {
    ['instructor' => $instructor, 'quiz' => $quiz] = qatCourseQuiz();
    $configure = app(ConfigureQuiz::class);
    $manage = app(ManageQuestions::class);

    // O-020: require_pass without an explicit pass mark is accepted; the platform default (60%) then applies.
    $noMark = $configure($instructor, $quiz, [
        'time_limit_minutes' => 30,
        'pass_mark_percent' => null,
        'max_attempts' => 2,
        'result_visibility' => 'full',
        'randomize' => true,
        'progression' => 'require_pass',
    ]);
    expect($noMark->pass_mark_percent)->toBeNull();

    // Valid configuration saves and audits
    $saved = $configure($instructor, $quiz, [
        'time_limit_minutes' => 30,
        'pass_mark_percent' => 60,
        'max_attempts' => 2,
        'result_visibility' => 'score_only',
        'randomize' => true,
        'progression' => 'require_pass',
    ]);
    expect($saved->pass_mark_percent)->toBe(60)->and($saved->randomize)->toBeTrue();
    expect(Activity::query()->where('event', 'quiz.settings_changed')->count())->toBe(2); // O-020: the no-mark save above is audited too

    // Guard query exposes only active short_answer & essay prompts without options/keys/explanations
    $manage->add($instructor, $quiz, [
        'type' => 'single',
        'prompt' => ['en' => 'Choice question'],
        'explanation' => ['en' => 'Secret explanation'],
        'points' => 1,
        'options' => [['text' => ['en' => 'Secret Key'], 'is_correct' => true], ['text' => ['en' => 'No'], 'is_correct' => false]],
    ]);
    $manage->add($instructor, $quiz, [
        'type' => 'short_answer',
        'prompt' => ['en' => 'Short prompt', 'ar' => 'سؤال قصير'],
        'explanation' => ['en' => 'Hidden short explanation'],
        'points' => 2,
        'options' => [['text' => ['en' => 'Secret Accepted Answer'], 'is_correct' => true]],
    ]);
    $inactiveEssay = $manage->add($instructor, $quiz, [
        'type' => 'essay',
        'prompt' => ['en' => 'Inactive essay'],
        'points' => 5,
    ]);
    $manage->setActive($instructor, $inactiveEssay, false);

    $open = QuizGuardQuestions::openFor($quiz);
    $encoded = json_encode($open->values()->all(), JSON_THROW_ON_ERROR);

    expect($open)->toHaveCount(1)
        ->and($open->first()['type'])->toBe('short_answer')
        ->and($encoded)->toContain('Short prompt')
        ->and($encoded)->not->toContain('Choice question')
        ->and($encoded)->not->toContain('Inactive essay')
        ->and($encoded)->not->toContain('Secret Key')
        ->and($encoded)->not->toContain('Secret Accepted Answer')
        ->and($encoded)->not->toContain('Hidden short explanation')
        ->and($encoded)->not->toContain('is_correct')
        ->and($encoded)->not->toContain('options');
})->group('T-P6-01', 'A1');

it('enforces ManageQuiz and ManageQuestions authorization across roles and scopes', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz] = qatCourseQuiz();
    ['quiz' => $foreignQuiz] = qatCourseQuiz();
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);
    $otherInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $contentManager = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('admin');

    $manage = app(ManageQuestions::class);
    $configure = app(ConfigureQuiz::class);

    // Allowed staff
    foreach ([$admin, $superAdmin, $instructor] as $allowed) {
        expect($configure($allowed, $quiz, ['result_visibility' => 'full', 'progression' => 'after_submission']))->toBeInstanceOf(Quiz::class);
    }

    // Forbidden actors
    foreach ([$viewInstructor, $otherInstructor, $contentManager, $student, $parent, $suspended] as $denied) {
        expect(fn () => $manage->add($denied, $quiz, ['type' => 'essay', 'prompt' => ['en' => 'X'], 'points' => 1]))
            ->toThrow(AuthorizationException::class);
        expect(fn () => $configure($denied, $quiz, ['result_visibility' => 'full', 'progression' => 'after_submission']))
            ->toThrow(AuthorizationException::class);

        $this->actingAs($denied);
        Filament::setCurrentPanel('instructor');
        $response = $this->get("/instructor/courses/{$course->id}/quizzes/{$quiz->id}");
        expect($response->getStatusCode())->toBeIn([403, 404]);
    }

    // Foreign quiz question cannot be edited or toggled via this quiz page
    $foreignQ = $manage->add(User::factory()->create(['status' => 'active'])->assignRole('admin'), $foreignQuiz, [
        'type' => 'essay', 'prompt' => ['en' => 'Foreign'], 'points' => 1,
    ]);
    $this->actingAs($instructor);
    Filament::setCurrentPanel('instructor');
    Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $quiz->id])
        ->call('toggleQuestion', $foreignQ->id)
        ->assertNotFound();
})->group('T-P6-01', 'A2');

it('authors, edits, reorders, toggles, and safely previews questions in ManageQuiz and links from ManageCurriculum', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz, 'section' => $section] = qatCourseQuiz();
    app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Sketch Task']]);
    $this->actingAs($instructor);
    Filament::setCurrentPanel('instructor');

    // ManageCurriculum renders data-edit-quiz for quiz items only
    Livewire::test(ManageCurriculum::class, ['record' => $course->id])
        ->assertOk()
        ->assertSee('data-edit-quiz', false)
        ->assertSee(route('filament.instructor.resources.courses.quiz', ['record' => $course->id, 'quiz' => $quiz->id]), false);

    // Empty state renders first
    $page = Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $quiz->id])
        ->assertOk()
        ->assertSee('data-quiz-empty', false);

    // Invalid single choice halts with danger notification and writes nothing
    $page->callAction('addQuestion', [
        'type' => 'single',
        'prompt' => ['en' => 'Broken choice'],
        'points' => 2,
        'options' => [
            ['text' => ['en' => 'A'], 'is_correct' => true],
            ['text' => ['en' => 'B'], 'is_correct' => true],
        ],
    ])->assertActionHalted('addQuestion')->assertNotified()->unmountAction();
    expect($quiz->questions()->count())->toBe(0);

    // Valid true_false question & short_answer question
    $page->callAction('addQuestion', [
        'type' => 'true_false',
        'prompt' => ['en' => 'Watercolors are transparent'],
        'explanation' => ['en' => 'HIDDEN_TF_EXPLANATION'],
        'points' => 4,
        'true_is_correct' => true,
    ])->assertHasNoActionErrors();

    $page->callAction('addQuestion', [
        'type' => 'short_answer',
        'prompt' => ['en' => 'Primary colors count'],
        'points' => 6,
        'options' => [['text' => ['en' => 'SECRET_ACCEPTED_THREE']]],
    ])->assertHasNoActionErrors();

    $page->callAction('settings', [
        'time_limit_minutes' => 15,
        'pass_mark_percent' => 60,
        'max_attempts' => 3,
        'result_visibility' => 'full',
        'randomize' => false,
        'progression' => 'require_pass',
    ])->assertHasNoActionErrors();

    $questions = $quiz->questions()->get();
    expect($questions)->toHaveCount(2);

    // Summary reflects 2 questions, 10 marks, 60% (6)
    $page->call('$refresh')
        ->assertSee('data-question-count', false)
        ->assertSee('60% (6)')
        ->assertSee('data-correct', false);

    // Preview section hides answer keys, explanations, and accepted short answers
    $page->callAction('preview')->assertSee('data-quiz-preview', false);
    preg_match('/<section[^>]*data-quiz-preview[^>]*>.*?<\/section>/s', $page->html(), $previewMatch);
    expect($previewMatch[0] ?? '')->not->toBe('')
        ->toContain('Primary colors count')
        ->not->toContain('HIDDEN_TF_EXPLANATION')
        ->not->toContain('SECRET_ACCEPTED_THREE')
        ->not->toContain('data-correct');

    // Toggle inactive hides from preview and marks data-question-active="false"
    $page->call('toggleQuestion', $questions[1]->id)
        ->assertSee('data-question-active="false"', false);
    preg_match('/<section[^>]*data-quiz-preview[^>]*>.*?<\/section>/s', $page->html(), $previewAfterToggle);
    expect($previewAfterToggle[0] ?? '')->not->toBe('')
        ->not->toContain('Primary colors count');

    // Reorder works without error
    $page->call('moveQuestion', $questions[1]->id, 'up')->assertOk();
})->group('T-P6-01', 'A1', 'A2');
