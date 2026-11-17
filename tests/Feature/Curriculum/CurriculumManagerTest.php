<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\MoveCurriculumItem;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function cmUser(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

function cmCourse(?User $instructor = null, string $scope = 'manage'): Course
{
    $course = Course::query()->create(['title' => ['en' => 'Course'], 'slug_ar' => 'c-'.uniqid(), 'slug_en' => 'c-'.uniqid()]);
    if ($instructor !== null) {
        $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => $scope]);
    }

    return $course;
}

/** @return array{0: Course, 1: User, 2: list<CourseSection>, 3: array<string, CurriculumItem>} two sections, three lessons */
function cmBuilt(): array
{
    $instructor = cmUser('instructor_academy');
    $course = cmCourse($instructor);
    $one = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'One']]);
    $two = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Two']]);
    $a = app(ManageLessons::class)->add($instructor, $one, ['title' => ['en' => 'A']]);
    $b = app(ManageLessons::class)->add($instructor, $one, ['title' => ['en' => 'B']]);
    $c = app(ManageLessons::class)->add($instructor, $two, ['title' => ['en' => 'C']]);

    return [$course, $instructor, [$one, $two], ['a' => $a, 'b' => $b, 'c' => $c]];
}

function cmOrder(Course $course): array
{
    return CurriculumOutline::forManager($course)->map(static fn ($s) => $s->items->map(static fn ($i) => $i->itemable->getTranslation('title', 'en'))->all())->all();
}

it('adds sections and lessons at the end, always as drafts, with no sort column on lessons', function (): void {
    [$course, , $sections, $items] = cmBuilt();

    expect(Schema::hasColumn('lessons', 'sort'))->toBeFalse()
        ->and($sections[0]->sort)->toBe(1)->and($sections[1]->sort)->toBe(2)
        ->and($items['a']->sort)->toBe(1)->and($items['b']->sort)->toBe(2)->and($items['c']->sort)->toBe(1)
        ->and(collect($items)->every(fn (CurriculumItem $i) => $i->status === 'draft'))->toBeTrue()
        ->and(CourseSection::query()->where('course_id', $course->id)->where('published', true)->exists())->toBeFalse();
})->group('T-P3-04', 'A4', 'YG-GAP-CNT-001');

it('persists a reorder and the manager order equals the student order for published items', function (): void {
    [$course, $instructor, $sections, $items] = cmBuilt();

    app(ReorderCurriculum::class)($instructor, $course, [
        ['section_id' => $sections[1]->id, 'items' => [$items['c']->id, $items['a']->id]],
        ['section_id' => $sections[0]->id, 'items' => [$items['b']->id]],
    ]);

    expect(cmOrder($course))->toBe([['C', 'A'], ['B']]);

    app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id, $items['b']->id, $items['c']->id], 'published');
    foreach ($sections as $section) {
        app(ManageSections::class)->setPublished($instructor, $section->refresh(), true);
    }

    $manager = collect(CurriculumOutline::forManager($course))->flatMap(fn ($s) => $s->items->pluck('id'))->map(fn ($id) => (int) $id)->all();
    expect(CurriculumOutline::studentOrder($course))->toBe($manager)
        ->and(CurriculumOutline::studentOrder($course))->toBe([$items['c']->id, $items['a']->id, $items['b']->id]);
})->group('T-P3-04', 'A1', 'YG-GAP-CNT-001');

it('rejects a stale or partial reorder without changing anything', function (): void {
    [$course, $instructor, $sections, $items] = cmBuilt();

    foreach ([
        [['section_id' => $sections[0]->id, 'items' => [$items['a']->id]]],
        [['section_id' => $sections[0]->id, 'items' => [$items['a']->id, $items['b']->id]], ['section_id' => $sections[1]->id, 'items' => []]],
        [['section_id' => $sections[0]->id, 'items' => [$items['a']->id, $items['a']->id, $items['b']->id]], ['section_id' => $sections[1]->id, 'items' => [$items['c']->id]]],
        [],
    ] as $layout) {
        expect(fn () => app(ReorderCurriculum::class)($instructor, $course, $layout))->toThrow(ValidationException::class);
    }
    expect(cmOrder($course))->toBe([['A', 'B'], ['C']]);
})->group('T-P3-04', 'A1');

it('moves items up and down with the keyboard-friendly action, crossing section edges', function (): void {
    [$course, $instructor, , $items] = cmBuilt();

    app(MoveCurriculumItem::class)($instructor, $items['b'], 'up');
    expect(cmOrder($course))->toBe([['B', 'A'], ['C']]);

    app(MoveCurriculumItem::class)($instructor, $items['a'], 'down');
    expect(cmOrder($course))->toBe([['B'], ['A', 'C']]);

    app(MoveCurriculumItem::class)($instructor, $items['a'], 'up');
    expect(cmOrder($course))->toBe([['B', 'A'], ['C']]);
})->group('T-P3-04', 'A1');

it('forbids reorder, publishing and edits by instructors who do not manage the course', function (): void {
    [$course, , $sections, $items] = cmBuilt();
    $unassigned = cmUser('instructor_academy');
    $viewOnly = cmUser('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    $layout = [['section_id' => $sections[0]->id, 'items' => [$items['b']->id, $items['a']->id]], ['section_id' => $sections[1]->id, 'items' => [$items['c']->id]]];

    foreach ([$unassigned, $viewOnly, cmUser('student'), cmUser('finance')] as $actor) {
        expect(fn () => app(ReorderCurriculum::class)($actor, $course, $layout))->toThrow(AuthorizationException::class);
        expect(fn () => app(SetCurriculumStatus::class)($actor, $course, [$items['a']->id], 'published'))->toThrow(AuthorizationException::class);
        expect(fn () => app(ManageSections::class)->add($actor, $course, ['title' => ['en' => 'x']]))->toThrow(AuthorizationException::class);
        expect(fn () => app(ManageLessons::class)->add($actor, $sections[0], ['title' => ['en' => 'x']]))->toThrow(AuthorizationException::class);
        expect(fn () => app(ManageLessons::class)->archive($actor, $items['a']->itemable))->toThrow(AuthorizationException::class);
    }
    expect(cmOrder($course))->toBe([['A', 'B'], ['C']])->and(Activity::query()->where('event', 'like', 'curriculum.%')->count())->toBe(0);
})->group('T-P3-04', 'A3', 'I02');

it('cannot touch another course through a crafted item id', function (): void {
    [$course, $instructor] = cmBuilt();
    [, , , $otherItems] = cmBuilt();

    expect(fn () => app(SetCurriculumStatus::class)($instructor, $course, [$otherItems['a']->id], 'published'))->toThrow(ValidationException::class);
    expect($otherItems['a']->refresh()->status)->toBe('draft');
})->group('T-P3-04', 'A3', 'I02');

it('publishes and unpublishes in bulk and audits each change', function (): void {
    [$course, $instructor, $sections, $items] = cmBuilt();

    $changed = app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id, $items['b']->id], 'published');
    expect($changed)->toBe(2)->and($items['a']->refresh()->status)->toBe('published')->and($items['c']->refresh()->status)->toBe('draft');
    expect(app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id], 'published'))->toBe(0);

    app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id], 'draft');
    expect($items['a']->refresh()->status)->toBe('draft');

    app(ManageSections::class)->setPublished($instructor, $sections[1], true);
    expect($items['c']->refresh()->status)->toBe('published')->and($sections[1]->refresh()->published)->toBeTrue();

    $events = Activity::query()->where('event', 'like', 'curriculum.%')->orderBy('id')->pluck('event')->all();
    expect($events)->toBe(['curriculum.items_published', 'curriculum.items_published', 'curriculum.items_unpublished', 'curriculum.section_published']);
    $first = Activity::query()->where('event', 'curriculum.items_published')->first();
    expect($first->causer_id)->toBe($instructor->id)->and($first->properties->get('context'))->toBe(['item_ids' => [$items['a']->id, $items['b']->id]]);
})->group('T-P3-04', 'A4', 'ED-024');

it('hides archived lessons and sections from students but keeps the records', function (): void {
    [$course, $instructor, $sections, $items] = cmBuilt();
    app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id, $items['b']->id, $items['c']->id], 'published');
    foreach ($sections as $section) {
        app(ManageSections::class)->setPublished($instructor, $section->refresh(), true);
    }
    expect(CurriculumOutline::studentOrder($course))->toHaveCount(3);

    app(ManageLessons::class)->archive($instructor, $items['b']->itemable);
    expect(CurriculumOutline::studentOrder($course))->toBe([$items['a']->id, $items['c']->id])
        ->and(Lesson::query()->find($items['b']->itemable_id))->not->toBeNull()
        ->and(CurriculumItem::query()->find($items['b']->id))->not->toBeNull()
        ->and(CurriculumOutline::archivedItems($course)->pluck('id')->all())->toBe([$items['b']->id])
        ->and(cmOrder($course))->toBe([['A'], ['C']]);

    app(ManageSections::class)->archive($instructor, $sections[1]);
    expect(CurriculumOutline::studentOrder($course))->toBe([$items['a']->id])
        ->and(CurriculumItem::query()->find($items['c']->id))->not->toBeNull();
    expect(Activity::query()->whereIn('event', ['curriculum.lesson_archived', 'curriculum.section_archived'])->count())->toBe(2);
})->group('T-P3-04', 'A2');

it('keeps draft items and unpublished sections away from students', function (): void {
    [$course, $instructor, $sections, $items] = cmBuilt();

    expect(CurriculumOutline::studentOrder($course))->toBe([]);
    app(SetCurriculumStatus::class)($instructor, $course, [$items['a']->id], 'published');
    expect(CurriculumOutline::studentOrder($course))->toBe([]);
    app(ManageSections::class)->setPublished($instructor, $sections[0], true);
    expect(CurriculumOutline::studentOrder($course))->toBe([$items['a']->id, $items['b']->id]);
})->group('T-P3-04', 'A2', 'A4');

it('validates titles, types and durations', function (): void {
    [, $instructor, $sections] = cmBuilt();

    expect(fn () => app(ManageSections::class)->add($instructor, $sections[0]->course, ['title' => ['ar' => ' ', 'en' => '']]))->toThrow(ValidationException::class);
    expect(fn () => app(ManageLessons::class)->add($instructor, $sections[0], ['title' => ['en' => 'x'], 'type' => 'hologram']))->toThrow(ValidationException::class);
    expect(fn () => app(ManageLessons::class)->add($instructor, $sections[0], ['title' => ['en' => 'x'], 'duration_seconds' => -5]))->toThrow(ValidationException::class);

    $item = app(ManageLessons::class)->add($instructor, $sections[0], ['title' => ['ar' => 'درس', 'en' => 'Lesson'], 'type' => 'audio', 'duration_seconds' => 600, 'is_preview' => true]);
    expect($item->itemable->type)->toBe('audio')->and($item->itemable->duration_seconds)->toBe(600)->and($item->itemable->is_preview)->toBeTrue();

    app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['en' => 'Renamed']]);
    expect($item->itemable->refresh()->getTranslation('title', 'en'))->toBe('Renamed');
})->group('T-P3-04', 'A4');

it('keeps the AI flag on curriculum items on by default', function (): void {
    [, , , $items] = cmBuilt();

    expect($items['a']->refresh()->ai_enabled)->toBeTrue()->and($items['a']->required)->toBeTrue();
})->group('T-P3-04', 'A4', 'ai');

it('refuses a malformed reorder payload before touching anything', function (mixed $layout): void {
    [$course, $instructor] = cmBuilt();

    expect(fn () => app(ReorderCurriculum::class)($instructor, $course, $layout))->toThrow(ValidationException::class);
    expect(cmOrder($course))->toBe([['A', 'B'], ['C']]);
})->with([
    'missing items' => [[['section_id' => 1]]],
    'missing section' => [[['items' => [1]]]],
    'text ids' => [[['section_id' => 'x', 'items' => ['y']]]],
    'not a list' => [['a' => ['section_id' => 1, 'items' => []]]],
])->group('T-P3-04', 'A1', 'A3');
