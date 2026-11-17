<?php

declare(strict_types=1);

use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\UnavailableReason;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\AccessDecision;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Settings\PlatformFlag;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** @return array{0: Course, 1: array<string, CurriculumItem>, 2: User} a sequential course: A, B (optional), C, quiz, D; learner enrolled */
function alBuild(string $mode = 'sequential', bool $enroll = true): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Layers'], 'slug_ar' => 'al-'.uniqid(), 'slug_en' => 'al-'.uniqid(), 'status' => 'published', 'access_mode' => 'free', 'sequence_mode' => $mode]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $items = [];
    foreach (['a' => 'Lesson A', 'b' => 'Lesson B', 'c' => 'Lesson C'] as $key => $title) {
        $items[$key] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title], 'type' => 'text']);
    }
    $items['quiz'] = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);
    $items['d'] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson D'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($i) => $i->id, array_values($items)), 'published');
    $items['b']->refresh()->update(['required' => false]);

    $learner = User::factory()->create()->assignRole('student');
    if ($enroll) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }

    return [$course->refresh(), array_map(fn ($i) => $i->refresh(), $items), $learner];
}

function alDecide(User $learner, CurriculumItem $item): AccessDecision
{
    return app(EntitlementService::class)->canAccessItem($learner, $item->refresh());
}

it('covers every layer and reason code in one ordered decision', function (callable $arrange, string $item, ?string $reason): void {
    [$course, $items, $learner] = alBuild();
    $arrange($course, $items, $learner);

    $decision = alDecide($learner, $items[$item]);

    expect($decision->reason)->toBe($reason)->and($decision->allowed)->toBe($reason === null);
    if ($reason !== null) {
        expect($decision->message())->not->toBeEmpty();
    }
})->with([
    'first lesson of a sequence is open' => [fn () => null, 'a', null],
    'second lesson is locked until the first is done' => [fn () => null, 'c', 'sequence_locked'],
    'the optional lesson still waits for earlier required ones' => [fn ($c, $i, $l) => null, 'b', 'sequence_locked'],
    'done first lesson opens the next required one' => [fn ($c, $i, $l) => app(RecordProgress::class)->markComplete($l, $i['a']), 'c', null],
    'the quiz waits for every required lesson before it' => [fn ($c, $i, $l) => app(RecordProgress::class)->markComplete($l, $i['a']), 'quiz', 'sequence_locked'],
    'lessons after the quiz open once required lessons are done' => [function ($c, $i, $l): void {
        app(RecordProgress::class)->markComplete($l, $i['a']);
        app(RecordProgress::class)->markComplete($l, $i['c']);
    }, 'd', null],
    'optional lessons never block' => [function ($c, $i, $l): void {
        app(RecordProgress::class)->markComplete($l, $i['a']);
        app(RecordProgress::class)->markComplete($l, $i['c']);
    }, 'quiz', null],
    'drip comes before sequence' => [fn ($c, $i) => $i['c']->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]), 'c', 'drip_locked'],
    'course not open' => [fn ($c) => $c->update(['status' => 'suspended']), 'a', 'course_unpublished'],
    'item not published' => [fn ($c, $i) => $i['a']->update(['status' => 'draft']), 'a', 'item_unpublished'],
    'not enrolled' => [fn ($c, $i, $l) => Entitlement::query()->delete(), 'a', 'not_entitled'],
])->group('T-P4-09', 'A1', 'YG-GAP-LRN-001', 'YG-GAP-LRN-002', 'ED-025', 'ED-005');

it('keeps a free course open in any order', function (): void {
    [, $items, $learner] = alBuild('free');

    expect(alDecide($learner, $items['c'])->allowed)->toBeTrue()->and(alDecide($learner, $items['d'])->allowed)->toBeTrue();
})->group('T-P4-09', 'A1');

it('names the first unfinished lesson and reopens the next one the moment it is completed', function (): void {
    [, $items, $learner] = alBuild();

    $locked = alDecide($learner, $items['c']);
    expect($locked->detail)->toBe('Lesson A')->and($locked->message())->toContain('Lesson A');
    app()->setLocale('ar');
    expect(alDecide($learner, $items['c'])->message())->toContain('Lesson A')->and(alDecide($learner, $items['c'])->message())->toContain('أنهِ');
    app()->setLocale('en');

    app(RecordProgress::class)->markComplete($learner, $items['a']); // same request: the cached answer must not go stale
    expect(alDecide($learner, $items['c'])->allowed)->toBeTrue();
})->group('T-P4-09', 'A1');

it('shows the locked state with the reason and never the content or files', function (): void {
    [$course, $items, $learner] = alBuild();
    $items['c']->itemable->update(['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'SECRET LESSON BODY']]]]]]);

    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['c']->id}")->assertOk()
        ->assertSee('data-locked-reason', false)->assertSee('Lesson A')->assertDontSee('SECRET LESSON BODY')->assertDontSee('data-lesson-text', false)->assertDontSee('<video', false);

    $this->postJson("/learn/{$course->id}/{$items['c']->id}/progress", ['position' => 5])->assertNotFound();
    $this->postJson("/learn/{$course->id}/{$items['c']->id}/complete")->assertNotFound();

    $list = $this->actingAs($learner)->get("/learn/{$course->id}/{$items['a']->id}")->assertOk();
    $list->assertSee('data-lock-reason', false)->assertSee('Finish');

    app(RecordProgress::class)->markComplete($learner, $items['a']);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['c']->id}")->assertOk()->assertSee('SECRET LESSON BODY')->assertDontSee('data-locked-reason', false);
})->group('T-P4-09', 'A2');

it('guards lesson files and the assistant with the same decision', function (): void {
    [, $items, $learner] = alBuild();
    $lesson = $items['c']->itemable;
    $lesson->forceFill(['type' => 'video'])->save();
    LessonCurriculumDocument::query()->create(['lesson_id' => $lesson->id, 'version' => 1, 'path' => 'x.pdf', 'sha256' => str_repeat('a', 64), 'size' => 1, 'pages' => 1, 'status' => 'ready', 'active' => true]);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    config(['ai.adult_age' => 18]);
    $learner->forceFill(['birth_year' => now()->year - 30, 'birth_month' => 1])->save();
    $service = app(EntitlementService::class);
    $eligibility = fn () => app(AssistantEligibility::class)->check($learner->refresh(), $lesson->refresh());

    expect($service->canAccessLessonFiles($learner, $lesson))->toBeFalse()->and($eligibility()->reason)->toBe(UnavailableReason::NoEntitlement);

    app(RecordProgress::class)->markComplete($learner, $items['a']);
    expect($service->canAccessLessonFiles($learner, $lesson))->toBeTrue()->and($eligibility()->isAvailable())->toBeTrue();
})->group('T-P4-09', 'A1');

it('lets staff set the lesson order in the course basics', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin);
    $course = Course::query()->create(['title' => ['en' => 'Order'], 'slug_ar' => 'o-'.uniqid(), 'slug_en' => 'o-'.uniqid()]);
    expect($course->refresh()->sequence_mode)->toBe('free');

    $updated = app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Order'], 'sequence_mode' => 'sequential'], $course, 1);
    expect($updated->sequence_mode)->toBe('sequential');
    expect(fn () => app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Order'], 'sequence_mode' => 'random'], $course, 1))->toThrow(ValidationException::class);
})->group('T-P4-09', 'A1');
