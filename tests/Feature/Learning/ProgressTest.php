<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecalculateCourseProgress;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\ContinueLearning;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** @return array{0: Course, 1: array<string, CurriculumItem>, 2: User, 3: User} a course with a 100 s video, a text lesson and a quiz, and an enrolled learner */
function pgBuild(bool $enroll = true): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Progress'], 'slug_ar' => 'pg-'.uniqid(), 'slug_en' => 'pg-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Video'], 'type' => 'video', 'duration_seconds' => 100]);
    $text = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading'], 'type' => 'text']);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Quiz']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $text->id, $quiz->id], 'published');

    $learner = User::factory()->create()->assignRole('student');
    if ($enroll) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }

    return [$course->refresh(), ['video' => $video->refresh(), 'text' => $text->refresh(), 'quiz' => $quiz->refresh()], $learner, $instructor];
}

it('does not let a client complete a video by sending the end position or by marking it done', function (): void {
    [, $items, $learner] = pgBuild();
    $record = app(RecordProgress::class);

    $row = $record->heartbeat($learner, $items['video'], 100); // jumps straight to the end
    expect($row->status)->toBe(LessonProgress::IN_PROGRESS)->and($row->furthest_seconds)->toBe(30)->and($row->percent)->toBe(30);

    expect(fn () => $record->markComplete($learner, $items['video']))->toThrow(ValidationException::class);
    expect(LessonProgress::query()->where('status', 'completed')->count())->toBe(0);
})->group('T-P4-04', 'A1');

it('completes a video once it has really been played far enough and keeps it complete', function (): void {
    [, $items, $learner] = pgBuild();
    $record = app(RecordProgress::class);

    $record->heartbeat($learner, $items['video'], 20);
    $this->travelTo(now()->addSeconds(40));
    $row = $record->heartbeat($learner, $items['video'], 70);
    expect($row->status)->toBe('in_progress')->and($row->percent)->toBe(70);

    $this->travelTo(now()->addSeconds(40));
    $row = $record->heartbeat($learner, $items['video'], 95);
    expect($row->status)->toBe('completed')->and($row->completion_source)->toBe('auto')->and($row->completed_at)->not->toBeNull();

    $this->travelTo(now()->addSeconds(10));
    $again = $record->heartbeat($learner, $items['video'], 5); // rewinding never undoes completion
    expect($again->status)->toBe('completed')->and($again->position_seconds)->toBe(5)->and($again->furthest_seconds)->toBe(95);
})->group('T-P4-04', 'A1');

it('lets a reading lesson be marked done by hand and a video of unknown length too', function (): void {
    [$course, $items, $learner, $instructor] = pgBuild();
    $record = app(RecordProgress::class);

    expect($record->markComplete($learner, $items['text'])->completion_source)->toBe('manual');
    expect($record->markComplete($learner, $items['text'])->status)->toBe('completed'); // twice is fine

    $items['video']->itemable->update(['duration_seconds' => null]);
    expect($record->markComplete($learner, $items['video']->refresh())->status)->toBe('completed');

    expect(fn () => $record->markComplete($learner, $items['quiz']))->toThrow(ValidationException::class);
})->group('T-P4-04', 'A1');

it('refuses progress from learners without access and from other courses', function (): void {
    [$course, $items, $stranger] = pgBuild(enroll: false);

    expect(fn () => app(RecordProgress::class)->heartbeat($stranger, $items['video'], 5))->toThrow(AuthorizationException::class);
    $this->actingAs($stranger)->postJson("/learn/{$course->id}/{$items['video']->id}/progress", ['position' => 5])->assertNotFound();
    $this->actingAs($stranger)->postJson("/learn/{$course->id}/{$items['text']->id}/complete")->assertNotFound();

    $other = Course::query()->create(['title' => ['en' => 'Other'], 'slug_ar' => 'o-'.uniqid(), 'slug_en' => 'o-'.uniqid(), 'status' => 'published']);
    app(GrantEntitlement::class)($stranger, $other, EntitlementSource::Free);
    $this->actingAs($stranger)->postJson("/learn/{$other->id}/{$items['video']->id}/progress", ['position' => 5])->assertNotFound();
    expect(LessonProgress::query()->count())->toBe(0);
})->group('T-P4-04', 'A1');

it('accepts heartbeats and manual marks over HTTP, validates input and throttles', function (): void {
    [$course, $items, $learner] = pgBuild();
    $this->actingAs($learner);

    $this->postJson("/learn/{$course->id}/{$items['video']->id}/progress", ['position' => 12])->assertOk()->assertJsonPath('position', 12)->assertJsonPath('course_percent', 0);
    $this->postJson("/learn/{$course->id}/{$items['video']->id}/progress", ['position' => -3])->assertUnprocessable();
    $this->postJson("/learn/{$course->id}/{$items['video']->id}/progress", [])->assertUnprocessable();
    $this->postJson("/learn/{$course->id}/{$items['video']->id}/complete")->assertUnprocessable()->assertJsonStructure(['message']);
    $this->postJson("/learn/{$course->id}/{$items['text']->id}/complete")->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('course_percent', 50);

    config(['learning.progress.heartbeats_per_minute' => 3]);
    foreach (range(1, 4) as $i) {
        $status = $this->postJson("/learn/{$course->id}/{$items['video']->id}/progress", ['position' => 13])->status();
    }
    expect($status)->toBe(429);
})->group('T-P4-04', 'A1');

it('resumes at the last position and continues at the first unfinished lesson', function (): void {
    [$course, $items, $learner] = pgBuild();
    $record = app(RecordProgress::class);
    $continue = app(ContinueLearning::class);

    expect($continue->for($learner, $course))->toBe(['item_id' => $items['video']->id, 'position_seconds' => 0]);

    $record->heartbeat($learner, $items['video'], 25);
    expect($continue->for($learner, $course))->toBe(['item_id' => $items['video']->id, 'position_seconds' => 25]);

    $this->actingAs($learner)->get("/learn/{$course->id}")->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $items['video']->id]));
    $this->get("/learn/{$course->id}/{$items['video']->id}")->assertOk()->assertSee('data-resume="25"', false)->assertSee('data-progress-url', false);

    $record->markComplete($learner, $items['text']); // the reading is the last thing touched and is finished
    expect($continue->for($learner, $course)['item_id'])->toBe($items['video']->id);
})->group('T-P4-04', 'A2');

it('keeps the course percentage right when lessons are added, archived or optional', function (): void {
    [$course, $items, $learner, $instructor] = pgBuild();
    $record = app(RecordProgress::class);
    $recalc = app(RecalculateCourseProgress::class);

    $record->markComplete($learner, $items['text']);
    $progress = $recalc($learner, $course);
    expect($progress->percent)->toBe(50)->and($progress->completed_required)->toBe(1)->and($progress->total_required)->toBe(2); // quiz not counted

    $section = $items['video']->section;
    $extra = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Extra'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$extra->id], 'published');
    expect($recalc($learner, $course)->percent)->toBe(33);

    $extra->refresh()->update(['required' => false]);
    expect($recalc($learner, $course)->percent)->toBe(50);

    app(ManageLessons::class)->archive($instructor, $items['video']->itemable);
    $done = $recalc($learner, $course);
    expect($done->percent)->toBe(100)->and($done->completed_at)->not->toBeNull();

    app(SetCurriculumStatus::class)($instructor, $course, [$extra->id], 'draft');
    $extra->refresh()->update(['required' => true, 'status' => 'published']);
    expect($recalc($learner, $course)->percent)->toBe(50)->and(CourseProgress::query()->count())->toBe(1)->and($recalc($learner, $course)->completed_at)->toBeNull();
})->group('T-P4-04', 'A3');

it('shows course progress and completed lessons on the player page', function (): void {
    [$course, $items, $learner] = pgBuild();
    app(RecordProgress::class)->markComplete($learner, $items['text']);

    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['text']->id}")->assertOk()
        ->assertSee('data-course-progress', false)->assertSee('50%')->assertSee('data-item-completed', false)->assertSee('data-lesson-completed', false);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertOk()->assertDontSee('data-player-complete', false);

    $other = User::factory()->create()->assignRole('student');
    $this->actingAs($other)->get("/learn/{$course->id}/{$items['text']->id}")->assertNotFound();
})->group('T-P4-04', 'A2');
