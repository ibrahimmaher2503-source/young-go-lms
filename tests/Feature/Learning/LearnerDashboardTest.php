<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Progress\Actions\RecordProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** @return array{0: Course, 1: list<CurriculumItem>} a free published course with two reading lessons */
function ldCourse(string $title): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => $title], 'slug_ar' => 'ld-'.uniqid(), 'slug_en' => 'ld-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $items = [
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title.' lesson one'], 'type' => 'text']),
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title.' lesson two'], 'type' => 'text']),
    ];
    app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($i) => $i->id, $items), 'published');

    return [$course, $items];
}

function ldEnroll(User $learner, Course $course): void
{
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
}

it('shows the learner only their own courses, progress and continue point', function (): void {
    [$mine, $mineItems] = ldCourse('Mine');
    [$other] = ldCourse('Someone elses');
    $learner = User::factory()->create(['name' => 'Sara Ahmed'])->assignRole('student');
    $stranger = User::factory()->create()->assignRole('student');
    ldEnroll($learner, $mine);
    ldEnroll($stranger, $other);
    app(RecordProgress::class)->markComplete($learner, $mineItems[0]->refresh());

    $this->actingAs($learner)->get('/my')->assertOk()
        ->assertSee('Welcome, Sara')->assertSee('Mine')->assertSee('50%')->assertSee('Mine lesson two')
        ->assertSee(route('learn.show', ['course' => $mine->id, 'item' => $mineItems[1]->id]), false)
        ->assertDontSee('Someone elses')
        ->assertSee('data-widget="live-slot"', false)->assertSee('data-widget="work-slot"', false)->assertSee('data-widget="certificates-slot"', false);

    $this->actingAs($learner)->get('/my/courses')->assertOk()->assertSee('Mine')->assertDontSee('Someone elses');
})->group('T-P4-05', 'A1');

it('scopes everything to the child while a parent acts for them', function (): void {
    [$kids, $kidItems] = ldCourse('Kids art');
    [$parents] = ldCourse('Parent only');
    $parent = User::factory()->create(['name' => 'Mona Parent'])->assignRole('parent');
    $child = User::factory()->create(['name' => 'Omar Child'])->assignRole('student');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);
    ldEnroll($child, $kids);
    ldEnroll($parent, $parents);

    $this->actingAs($parent)->get('/my')->assertOk()->assertSee('Parent only')->assertDontSee('Kids art');
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id])->get('/my')->assertOk()
        ->assertSee('Welcome, Omar')->assertSee('Kids art')->assertDontSee('Parent only')->assertSee('data-child-context', false);
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id])->get('/my/courses')->assertOk()->assertSee('Kids art')->assertDontSee('Parent only');
})->group('T-P4-05', 'A1');

it('filters my courses by in progress, completed and access ended', function (): void {
    [$active, $activeItems] = ldCourse('Active course');
    [$done, $doneItems] = ldCourse('Done course');
    [$ended, $endedItems] = ldCourse('Ended course');
    $learner = User::factory()->create()->assignRole('student');
    $admin = User::factory()->create()->assignRole('admin');
    foreach ([$active, $done, $ended] as $course) {
        ldEnroll($learner, $course);
    }
    foreach ($doneItems as $item) {
        app(RecordProgress::class)->markComplete($learner, $item->refresh());
    }
    app(RevokeEntitlement::class)($admin, Entitlement::query()->where('course_id', $ended->id)->firstOrFail(), 'access ended for test');

    $show = fn (?string $status) => $this->actingAs($learner)->get('/my/courses'.($status ? "?status={$status}" : ''));
    $show(null)->assertOk()->assertSee('Active course')->assertSee('Done course')->assertSee('Ended course');
    $show('in_progress')->assertSee('Active course')->assertDontSee('Done course')->assertDontSee('Ended course');
    $show('completed')->assertSee('Done course')->assertDontSee('Active course')->assertDontSee('Ended course');
    $show('expired')->assertSee('Ended course')->assertDontSee('Active course')->assertDontSee('Done course')->assertSee('Your access to this course has ended.');
    $show('bogus')->assertOk()->assertSee('Active course')->assertSee('Done course'); // unknown filter falls back to all
})->group('T-P4-05', 'A1');

it('shows calm empty states and sends students to the dashboard from home', function (): void {
    $learner = User::factory()->create()->assignRole('student');

    $this->actingAs($learner)->get('/my')->assertOk()->assertSee(__('learner.no_courses'))->assertSee('data-widget="continue-empty"', false);
    $this->actingAs($learner)->get('/my/courses?status=completed')->assertOk()->assertSee(__('learner.no_courses'));
    $this->actingAs($learner)->get('/home')->assertRedirect('/my');
    auth()->logout();
    $this->get('/my')->assertRedirect('/login');
})->group('T-P4-05', 'A1');

it('lists the latest notifications of the acting learner only', function (): void {
    $learner = User::factory()->create()->assignRole('student');
    $other = User::factory()->create()->assignRole('student');
    foreach ([[$learner, 'Your lesson is ready'], [$other, 'Secret for someone else']] as [$user, $message]) {
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => $message]]);
    }

    $this->actingAs($learner)->get('/my')->assertOk()->assertSee('Your lesson is ready')->assertDontSee('Secret for someone else')->assertSee('data-unread-dot', false);
})->group('T-P4-05', 'A1');
