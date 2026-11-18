<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\MoveCurriculumItem;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
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

/** @return array{0: Course, 1: User, 2: CourseSection} */
function aiItemsCourse(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Mixed'], 'slug_ar' => 'mx-'.uniqid(), 'slug_en' => 'mx-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);

    return [$course, $instructor, $section];
}

/** @return list<string> titles in manager order for one section */
function aiItemsManagerOrder(Course $course): array
{
    return CurriculumOutline::forManager($course)->flatMap(fn ($s) => $s->items->map(fn ($i) => $i->itemable->getTranslation('title', 'en')))->all();
}

it('places lessons, quizzes and assignments in one order shared by the manager and the student query', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson']]);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);
    $assignment = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'واجب', 'en' => 'Homework']]);
    $last = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Last']]);
    expect(aiItemsManagerOrder($course))->toBe(['Lesson', 'Quiz', 'Homework', 'Last']);

    app(ReorderCurriculum::class)($instructor, $course, [['section_id' => $section->id, 'items' => [$assignment->id, $lesson->id, $last->id, $quiz->id]]]);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id, $quiz->id, $assignment->id, $last->id], 'published');
    $section->update(['published' => true]);

    $student = CurriculumOutline::forStudents($course)->flatMap(fn ($s) => $s->items->map(fn ($i) => $i->itemable->getTranslation('title', 'en')))->all();

    expect(aiItemsManagerOrder($course))->toBe(['Homework', 'Lesson', 'Last', 'Quiz'])->and($student)->toBe(aiItemsManagerOrder($course))
        ->and(CurriculumOutline::studentOrder($course))->toBe([$assignment->id, $lesson->id, $last->id, $quiz->id])
        ->and(CurriculumItem::query()->where('itemable_type', 'quiz')->count())->toBe(1)->and($quiz->itemable)->toBeInstanceOf(Quiz::class)
        ->and($assignment->itemable)->toBeInstanceOf(Assignment::class)
        ->and($assignment->itemable->getTranslation('title', 'ar'))->toBe('واجب');
})->group('T-P3-11', 'A1', 'YG-GAP-CNT-001', 'ED-002');

it('moves an assessment up and down like any other item', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson']]);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);

    app(MoveCurriculumItem::class)($instructor, $quiz, 'up');
    expect(aiItemsManagerOrder($course))->toBe(['Quiz', 'Lesson']);

    app(MoveCurriculumItem::class)($instructor, $quiz->refresh(), 'down');
    expect(aiItemsManagerOrder($course))->toBe(['Lesson', 'Quiz']);
})->group('T-P3-11', 'A1', 'YG-GAP-CNT-001', 'ED-002');

it('archives an assessment out of both views and keeps the row for history', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);
    app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson']]);

    app(ManageAssessmentItems::class)->archive($instructor, $quiz);

    expect(aiItemsManagerOrder($course))->toBe(['Lesson'])->and(CurriculumOutline::archivedItems($course))->toHaveCount(1)
        ->and(Quiz::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'curriculum.quiz_archived')->exists())->toBeTrue();
})->group('T-P3-11', 'A1', 'YG-GAP-CNT-001', 'ED-002');

it('validates and authorizes', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    $action = app(ManageAssessmentItems::class);

    expect(fn () => $action->add($instructor, $section, 'poll', ['title' => ['en' => 'x']]))->toThrow(ValidationException::class)
        ->and(fn () => $action->add($instructor, $section, 'quiz', ['title' => []]))->toThrow(ValidationException::class)
        ->and(fn () => $action->add(User::factory()->create()->assignRole('student'), $section, 'quiz', ['title' => ['en' => 'x']]))->toThrow(AuthorizationException::class);

    $viewOnly = User::factory()->create()->assignRole('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    expect(fn () => $action->add($viewOnly, $section, 'assignment', ['title' => ['en' => 'x']]))->toThrow(AuthorizationException::class);

    $section->update(['archived_at' => now()]);
    expect(fn () => $action->add($instructor, $section->refresh(), 'quiz', ['title' => ['en' => 'x']]))->toThrow(ValidationException::class);
})->group('T-P3-11', 'A1', 'YG-GAP-CNT-001', 'ED-002');

it('adds and archives quizzes and assignments from the curriculum manager', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    $this->actingAs($instructor);

    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction('addAssessment', ['kind' => 'quiz', 'section_id' => $section->id, 'title' => ['en' => 'Unit quiz', 'ar' => 'اختبار الوحدة']])
        ->assertHasNoActionErrors()
        ->assertSee('Unit quiz')->assertSee(__('curriculum.types.quiz'));

    $item = CurriculumItem::query()->where('itemable_type', 'quiz')->firstOrFail();
    $test->callAction(TestAction::make('archiveAssessment')->arguments(['item' => $item->id]));

    expect(aiItemsManagerOrder($course))->toBe([])->and($item->itemable->refresh()->archived_at)->not->toBeNull();
})->group('T-P3-11', 'A1', 'YG-GAP-CNT-001', 'ED-002');

it('authors a course quiz and a lesson assignment without changing their curriculum placement', function (): void {
    [$course, $instructor, $section] = aiItemsCourse();
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson']]);
    [, $otherInstructor, $otherSection] = aiItemsCourse();
    $otherLesson = app(ManageLessons::class)->add($otherInstructor, $otherSection, ['title' => ['en' => 'Other lesson']]);
    $this->actingAs($instructor);

    $page = Livewire::test(ManageCurriculum::class, ['record' => $course->id])
        ->callAction('addAssessment', ['kind' => 'quiz', 'section_id' => $section->id, 'association' => 'course', 'title' => ['en' => 'Course quiz']])
        ->assertHasNoActionErrors()
        ->callAction('addAssessment', ['kind' => 'assignment', 'section_id' => $section->id, 'association' => 'lesson', 'associated_lesson_item_id' => $lesson->id, 'title' => ['en' => 'Lesson assignment']])
        ->assertHasNoActionErrors();

    $quiz = CurriculumItem::query()->where('itemable_type', 'quiz')->firstOrFail();
    $assignment = CurriculumItem::query()->where('itemable_type', 'assignment')->firstOrFail();
    expect($quiz->association)->toBe('course')->and($quiz->course_id)->toBe($course->id)
        ->and($assignment->association)->toBe('lesson')->and($assignment->associated_lesson_item_id)->toBe($lesson->id)
        ->and(aiItemsManagerOrder($course))->toBe(['Lesson', 'Course quiz', 'Lesson assignment']);

    expect(fn () => app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', [
        'title' => ['en' => 'Bad association'], 'association' => 'lesson', 'associated_lesson_item_id' => $otherLesson->id,
    ]))->toThrow(ValidationException::class);
})->group('T-P3-11', 'PRD-12', 'PRD-13', 'ED-002');
