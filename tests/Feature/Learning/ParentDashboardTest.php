<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\CourseProgress;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function pdCourse(string $title): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => $title], 'slug_ar' => 'pd-'.uniqid(), 'slug_en' => 'pd-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $items = [
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title.' one'], 'type' => 'text']),
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => $title.' two'], 'type' => 'text']),
    ];
    app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($i) => $i->id, $items), 'published');

    return [$course, $items];
}

function pdChild(User $parent, string $name, string $status = GuardianChildLink::STATUS_VERIFIED): User
{
    $child = User::factory()->create(['name' => $name])->assignRole('student');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => $status, 'method' => 'created_by_parent', 'verified_at' => now()]);

    return $child;
}

it('shows each linked child with their courses and progress and switches between them', function (): void {
    $parent = User::factory()->create(['name' => 'Mona Parent'])->assignRole('parent');
    $ahmed = pdChild($parent, 'Ahmed Kid');
    $layla = pdChild($parent, 'Layla Kid');
    [$art, $artItems] = pdCourse('Art course');
    [$music] = pdCourse('Music course');
    app(GrantEntitlement::class)($ahmed, $art, EntitlementSource::Free);
    app(GrantEntitlement::class)($layla, $music, EntitlementSource::Free);
    app(RecordProgress::class)->markComplete($ahmed, $artItems[0]->refresh());

    $this->actingAs($parent)->get('/parent')->assertOk()
        ->assertSee('Ahmed Kid')->assertSee('Layla Kid')->assertSee("Showing Ahmed Kid's progress now")->assertSee('Art course')->assertSee('50%')
        ->assertSee('Completed 1 of 2 lessons')->assertDontSee('Music course')->assertSee('data-widget="summary"', false);

    $this->get('/parent?child='.$layla->id)->assertOk()->assertSee("Showing Layla Kid's progress now")->assertSee('Music course')->assertDontSee('Art course');
})->group('T-P4-08', 'A1');

it('never shows a child who is not linked and verified to this parent', function (): void {
    $parent = User::factory()->create()->assignRole('parent');
    $mine = pdChild($parent, 'My Kid');
    $pending = pdChild($parent, 'Pending Kid', GuardianChildLink::STATUS_PENDING);
    $otherParent = User::factory()->create()->assignRole('parent');
    $theirs = pdChild($otherParent, 'Their Kid');
    $stranger = User::factory()->create(['name' => 'Stranger Kid'])->assignRole('student');
    [$secret] = pdCourse('Secret course');
    app(GrantEntitlement::class)($theirs, $secret, EntitlementSource::Free);

    $this->actingAs($parent)->get('/parent')->assertOk()->assertSee('My Kid')->assertDontSee('Pending Kid')->assertDontSee('Their Kid')->assertDontSee('Secret course');
    foreach ([$pending, $theirs, $stranger] as $other) {
        $this->actingAs($parent)->get('/parent?child='.$other->id)->assertNotFound();
    }
    $this->actingAs($parent)->get('/parent?child=999999')->assertNotFound();

    // a suspended child account drops out of the list too
    $mine->forceFill(['status' => 'suspended'])->save();
    $this->actingAs($parent)->get('/parent')->assertOk()->assertDontSee('My Kid')->assertSee('data-empty', false);
})->group('T-P4-08', 'A1');

it('is only for parents', function (): void {
    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/parent')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('admin'))->get('/parent')->assertForbidden();
    auth()->logout();
    $this->get('/parent')->assertRedirect('/login');
    $this->actingAs(User::factory()->create()->assignRole('parent'))->get('/home')->assertRedirect('/parent');
})->group('T-P4-08', 'A1');

it('renders no assistant conversation content and shows calm empty states', function (): void {
    $parent = User::factory()->create()->assignRole('parent');
    $child = pdChild($parent, 'Quiet Kid');
    [$course] = pdCourse('Plain course');
    app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);
    $lesson = Relation::getMorphedModel('lesson')::query()->latest('id')->firstOrFail();
    $conversation = DB::table('ai_conversations')->insertGetId(['student_id' => $child->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'document_version' => 1, 'locale' => 'en', 'style_tier' => 'kids', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('ai_messages')->insert(['conversation_id' => $conversation, 'role' => 'student', 'content' => 'PRIVATE QUESTION ABOUT SHADOWS', 'created_at' => now()]);

    $page = $this->actingAs($parent)->get('/parent')->assertOk();
    $page->assertDontSee('PRIVATE QUESTION')->assertDontSee('data-ai-slot', false)->assertSee('data-widget="live-slot"', false)->assertSee('data-widget="work-slot"', false)
        ->assertSee('data-widget="help-slot"', false)->assertSee('data-widget="results-slot"', false);

    $lonely = User::factory()->create()->assignRole('parent');
    $this->actingAs($lonely)->get('/parent')->assertOk()->assertSee(__('parent.no_children'))->assertSee('data-empty', false);
})->group('T-P4-08', 'A2');

it('offers the existing password-confirmed child context switch for the selected child', function (): void {
    $parent = User::factory()->create()->assignRole('parent');
    pdChild($parent, 'First child');
    $selected = pdChild($parent, 'Selected child');

    $this->actingAs($parent)->get('/parent?child='.$selected->id)->assertOk()
        ->assertSee(route('guardian.context.enter', $selected->id), false)
        ->assertSee(__('pages.switch_child'));
})->group('T-P4-08', 'YG-GAP-FAM-004', 'ISSUE-047');

it('paginates child courses without shrinking the summary or writing progress', function (): void {
    $parent = User::factory()->create()->assignRole('parent');
    pdChild($parent, 'First child');
    $child = pdChild($parent, 'Selected child');
    foreach (range(1, 13) as $number) {
        [$course] = pdCourse(sprintf('Child course %02d', $number));
        app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);
    }

    $url = '/parent?child='.$child->id;
    $this->actingAs($parent)->get($url)->assertOk()->assertSee('Child course 01')->assertDontSee('Child course 13');
    $this->get($url.'&page=2')->assertOk()->assertSee('Child course 13')->assertDontSee('Child course 01')
        ->assertViewHas('summary', fn (array $summary): bool => $summary['courses'] === 13 && $summary['lessons_total'] === 26);
    expect(CourseProgress::query()->count())->toBe(0);
})->group('T-P4-08', 'ISSUE-001', 'ISSUE-079');
