<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageSubtitleTracks;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
});

/** @return array{0: Course, 1: array<string, CurriculumItem>, 2: User, 3: User} a published course: video, locked (dated) lesson, preview text lesson, quiz */
function cpBuild(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Player course', 'ar' => 'دورة المشغل'], 'slug_ar' => 'p-'.uniqid(), 'slug_en' => 'p-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Basics', 'ar' => 'الأساسيات']]);
    $section->update(['published' => true]);

    Storage::disk('local')->put('media/clip.mp4', 'v');
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Intro video', 'ar' => 'فيديو المقدمة'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $video->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    $later = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Later lesson', 'ar' => 'درس لاحق'], 'type' => 'text']);
    $preview = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Free preview', 'ar' => 'معاينة'], 'type' => 'text', 'is_preview' => true]);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Unit quiz', 'ar' => 'اختبار']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $later->id, $preview->id, $quiz->id], 'published');
    $later->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);

    $vtt = tempnam(sys_get_temp_dir(), 'vtt');
    file_put_contents($vtt, "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello\n");
    app(ManageSubtitleTracks::class)->add($instructor, $video->itemable, 'en', 'English', new File($vtt));

    return [$course->refresh(), ['video' => $video, 'later' => $later, 'preview' => $preview, 'quiz' => $quiz], User::factory()->create()->assignRole('student'), User::factory()->create()->assignRole('admin')];
}

function cpEntitle(User $learner, Course $course): void
{
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
}

it('gives a learner without access a 404, not a 403, on lesson URLs', function (): void {
    [$course, $items, $learner] = cpBuild();

    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertNotFound();
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['later']->id}")->assertNotFound();
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['quiz']->id}")->assertNotFound();
    $this->actingAs($learner)->get("/learn/{$course->id}/999999")->assertNotFound();

    $other = Course::query()->create(['title' => ['en' => 'Other'], 'slug_ar' => 'o-'.uniqid(), 'slug_en' => 'o-'.uniqid(), 'status' => 'published']);
    cpEntitle($learner, $other);
    $this->actingAs($learner)->get("/learn/{$other->id}/{$items['video']->id}")->assertNotFound(); // item from another course
    auth()->logout();
    $this->get("/learn/{$course->id}/{$items['video']->id}")->assertRedirect();
})->group('T-P4-03', 'A1');

it('also hides lessons after access is revoked or the course is closed', function (): void {
    [$course, $items, $learner] = cpBuild();
    cpEntitle($learner, $course);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertOk();

    $course->update(['status' => 'suspended']);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertNotFound();
})->group('T-P4-03', 'A1');

it('plays the lesson for an entitled learner with captions, toolbar, shortcuts and navigation', function (): void {
    [$course, $items, $learner] = cpBuild();
    cpEntitle($learner, $course);

    $response = $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertOk();

    $response->assertSee('Intro video')->assertSee('<video', false)->assertSee('data-player', false)->assertSee('<track kind="captions"', false)
        ->assertSee(__('learn.speed'))->assertSee(__('learn.captions'))->assertSee(__('learn.fullscreen'))->assertSee(__('learn.shortcuts'))
        ->assertSee('data-watermark', false)->assertSee(route('learn.show', ['course' => $course->id, 'item' => $items['preview']->id]), false)
        ->assertDontSee('data-ai-slot', false); // assistant not available: no flag, no PDF
})->group('T-P4-03', 'A1');

it('shows why a dated lesson is locked to an entitled learner and lists the reason in the curriculum', function (): void {
    [$course, $items, $learner] = cpBuild();
    cpEntitle($learner, $course);

    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['later']->id}")->assertOk()
        ->assertSee(__('learn.locked_title'))->assertSee('data-locked-reason', false)->assertSee('This lesson opens on')
        ->assertDontSee('<video', false);

    $sections = app(EntitledCurriculumQuery::class)->for($learner, $course);
    $states = collect($sections[0]['items'])->pluck('state', 'title')->all();
    expect($states)->toBe(['Intro video' => 'open', 'Later lesson' => 'locked', 'Free preview' => 'open', 'Unit quiz' => 'open']);
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['video']->id}")->assertSee('data-lock-reason', false);
})->group('T-P4-03', 'A2');

it('opens only the free preview lesson to a learner who is not enrolled', function (): void {
    [$course, $items, $learner] = cpBuild();

    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['preview']->id}")->assertOk()->assertSee('Free preview');
    $sections = app(EntitledCurriculumQuery::class)->for($learner, $course);
    expect(collect($sections[0]['items'])->pluck('state', 'title')->all())->toBe(['Intro video' => 'locked', 'Later lesson' => 'locked', 'Free preview' => 'preview', 'Unit quiz' => 'locked']);
    $this->actingAs($learner)->get("/learn/{$course->id}")->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $items['preview']->id]));
})->group('T-P4-03', 'A2');

it('sends an enrolled learner from the course link to the first open lesson and shows quizzes as coming soon', function (): void {
    [$course, $items, $learner] = cpBuild();
    cpEntitle($learner, $course);

    $this->actingAs($learner)->get("/learn/{$course->id}")->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $items['video']->id]));
    $this->actingAs($learner)->get("/learn/{$course->id}/{$items['quiz']->id}")->assertOk()->assertSee(__('learn.assessment_soon'));

    $stranger = User::factory()->create()->assignRole('student');
    $this->actingAs($stranger)->get("/learn/{$course->id}")->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $items['preview']->id]));
})->group('T-P4-03', 'A2');

it('computes previous and next around the current item skipping locked ones', function (): void {
    $sections = [['id' => 1, 'items' => [['id' => 1, 'state' => 'open'], ['id' => 2, 'state' => 'locked'], ['id' => 3, 'state' => 'open']]]];

    expect(EntitledCurriculumQuery::neighbours($sections, 1))->toBe(['previous' => null, 'next' => 3])
        ->and(EntitledCurriculumQuery::neighbours($sections, 3))->toBe(['previous' => 1, 'next' => null])
        ->and(EntitledCurriculumQuery::neighbours($sections, 99))->toBe(['previous' => null, 'next' => null]);
})->group('T-P4-03', 'A2');

it('uses the child as the learner while a parent acts for them', function (): void {
    [$course, $items] = cpBuild();
    $parent = User::factory()->create()->assignRole('parent');
    $child = User::factory()->create()->assignRole('student');
    cpEntitle($child, $course);
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);

    $this->actingAs($parent)->get("/learn/{$course->id}/{$items['video']->id}")->assertNotFound(); // the parent themself has no access
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id])->get("/learn/{$course->id}/{$items['video']->id}")->assertOk();
})->group('T-P4-03', 'A1');
