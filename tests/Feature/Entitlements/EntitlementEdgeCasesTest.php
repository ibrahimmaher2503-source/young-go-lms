<?php

declare(strict_types=1);

// T-P4-01 | PRD A4, ED-005, I01 | A1 current beats ended and future rows, A2 exact refusal reasons, A3 one read path for course lists

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\EnrollInFreeCourse;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\CourseAccessSummary;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementHistory;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\LearnerCourses;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Filament\Resources\ManualGrants\ManualGrantResource;
use App\Filament\Resources\ManualGrants\Pages\ListManualGrants;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function eeCourse(string $title = 'Alpha', string $access = 'free', string $status = 'published', string $sequence = 'free'): Course
{
    return Course::query()->create([
        'title' => ['en' => $title, 'ar' => $title], 'slug_ar' => 'ee-'.uniqid(), 'slug_en' => 'ee-'.uniqid(),
        'status' => $status, 'access_mode' => $access, 'sequence_mode' => $sequence,
    ]);
}

function eeLearner(): User
{
    return User::factory()->create()->assignRole('student');
}

/** @return array<string, list<string>> */
function eeErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('lists one row per course with the current grant winning over older or future rows', function (): void {
    $learner = eeLearner();
    $alpha = eeCourse('Alpha');
    $beta = eeCourse('Beta');
    $gamma = eeCourse('Gamma');
    $delta = eeCourse('Delta');
    $grant = app(GrantEntitlement::class);
    $admin = User::factory()->create()->assignRole('admin');

    $grant($learner, $alpha, EntitlementSource::Purchase, 'alpha-old', now()->subDays(20), now()->subDays(10));
    $grant($learner, $alpha, EntitlementSource::Free, '', now()->subDay());
    $grant($learner, $beta, EntitlementSource::Free, '', now()->addDay()); // not started
    $revoked = $grant($learner, $gamma, EntitlementSource::Free);
    app(RevokeEntitlement::class)($admin, $revoked, 'refunded');
    $grant($learner, $delta, EntitlementSource::Purchase, 'delta-old', now()->subDays(30), now()->addDays(5));
    $grant($learner, $delta, EntitlementSource::Free, '', now()->subDay(), now()->addDays(20));

    $rows = LearnerCourses::for($learner);
    $byTitle = $rows->keyBy(fn (array $row): string => (string) $row['course']->title);

    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('course')->map->title->all())->toBe(['Alpha', 'Beta', 'Delta', 'Gamma'])
        ->and($byTitle['Alpha']['access'])->toBe('active')
        ->and($byTitle['Alpha']['source'])->toBe(EntitlementSource::Free->value)
        ->and($byTitle['Alpha']['ends_at'])->toBeNull()
        ->and($byTitle['Beta']['access'])->toBe('ended')
        ->and($byTitle['Gamma']['access'])->toBe('ended')
        ->and($byTitle['Delta']['access'])->toBe('active')
        ->and($byTitle['Delta']['source'])->toBe(EntitlementSource::Free->value)
        ->and($byTitle['Delta']['ends_at']->toDateString())->toBe(now()->addDays(20)->toDateString());
});

it('summarises current, ended and revoked grants by source and course', function (): void {
    $course = eeCourse('Stats');
    $other = eeCourse('Other');
    $admin = User::factory()->create()->assignRole('admin');
    $grant = app(GrantEntitlement::class);

    $grant(eeLearner(), $course, EntitlementSource::Free);
    $grant(eeLearner(), $course, EntitlementSource::Purchase, 'past', now()->subDays(3), now()->subDay());
    $revoked = $grant(eeLearner(), $course, EntitlementSource::ManualGrant, 'manual', null, now()->addMonth(), $admin, 'gift');
    app(RevokeEntitlement::class)($admin, $revoked, 'mistake');
    $grant(eeLearner(), $other, EntitlementSource::Free);

    $one = CourseAccessSummary::for($course->getKey());
    $all = CourseAccessSummary::for();

    expect($one)->toMatchArray(['active' => 1, 'expired' => 1, 'revoked' => 1])
        ->and($one['by_source']['free'])->toBe(['active' => 1, 'expired' => 0, 'revoked' => 0])
        ->and($one['by_source']['purchase'])->toBe(['active' => 0, 'expired' => 1, 'revoked' => 0])
        ->and($one['by_source']['manual_grant'])->toBe(['active' => 0, 'expired' => 0, 'revoked' => 1])
        ->and($all)->toMatchArray(['active' => 2, 'expired' => 1, 'revoked' => 1]);
});

it('does not count a not-yet-started grant as current access', function (): void {
    $course = eeCourse('Future');
    app(GrantEntitlement::class)(eeLearner(), $course, EntitlementSource::Free, '', now()->addWeek());

    expect(CourseAccessSummary::for($course->getKey())['active'])->toBe(0);
})->group('T-P4-01', 'A2');

it('returns no current entitlement for future-only or ended-only access and explains the refusal', function (): void {
    $learner = eeLearner();
    $future = eeCourse('Future only');
    $ended = eeCourse('Ended only');
    app(GrantEntitlement::class)($learner, $future, EntitlementSource::Free, '', now()->addDay());
    app(GrantEntitlement::class)($learner, $ended, EntitlementSource::Free, '', now()->subDays(3), now()->subDay());
    $service = app(EntitlementService::class);

    expect($service->currentFor($learner, $future))->toBeNull()
        ->and($service->canAccessCourse($learner, $future)->reason)->toBe('entitlement_expired')
        ->and($service->currentFor($learner, $ended))->toBeNull()
        ->and($service->canAccessCourse($learner, $ended)->reason)->toBe('entitlement_expired');
});

it('keeps an older current grant when a newer future grant exists', function (): void {
    $learner = eeLearner();
    $course = eeCourse('Shadow');
    $grant = app(GrantEntitlement::class);
    $current = $grant($learner, $course, EntitlementSource::Free, '', now()->subDays(10), now()->addDays(10));
    $grant($learner, $course, EntitlementSource::Purchase, 'future', now()->addDay());
    $service = app(EntitlementService::class);

    expect($service->currentFor($learner, $course)?->getKey())->toBe($current->getKey())
        ->and($service->canAccessCourse($learner, $course)->allowed)->toBeTrue();
});

it('treats private courses as open for their entitled learners', function (): void {
    $learner = eeLearner();
    $course = eeCourse('Private', 'paid', 'private');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Purchase, 'order-1');

    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue();
});

it('previews drip and sequence for a brand-new learner without reading real progress', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = eeCourse('Preview', 'paid', 'published', Course::SEQUENCE_SEQUENTIAL);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $first = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'First'], 'type' => 'text']);
    $second = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Second'], 'type' => 'text']);
    $drip = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Drip'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$first->id, $second->id], 'published');
    $drip->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);
    $service = app(EntitlementService::class);

    expect($service->previewDecision($first->refresh())->allowed)->toBeTrue()
        ->and($service->previewDecision($second->refresh())->reason)->toBe('sequence_locked')
        ->and($service->previewDecision($second->refresh())->detail)->toBe('First')
        ->and($service->previewDecision($drip->refresh())->reason)->toBe('drip_locked')
        ->and($service->previewDecision($drip->refresh())->allowed)->toBeFalse();
});

it('lets active users open published public resources without an entitlement', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = eeCourse('Public');
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Open'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $lesson = $item->refresh()->itemable;
    $learner = eeLearner();
    $service = app(EntitlementService::class);

    expect($service->canAccessPublicResource($learner, $lesson))->toBeTrue()
        ->and($service->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $learner->forceFill(['status' => 'suspended'])->save();
    expect($service->canAccessPublicResource($learner, $lesson))->toBeFalse();
});

it('opens a preview lesson but never its private files without a course entitlement', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = eeCourse('Preview files');
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Peek'], 'type' => 'video']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $item->refresh()->itemable->update(['is_preview' => true]);
    $learner = eeLearner();
    $service = app(EntitlementService::class);

    expect($service->canAccessItem($learner, $item->refresh())->allowed)->toBeTrue()
        ->and($service->canAccessLessonFiles($learner, $item->itemable->refresh()))->toBeFalse();

    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    expect($service->canAccessLessonFiles($learner, $item->itemable))->toBeTrue();
});

it('refuses a paid course before complaining that it is closed', function (): void {
    $learner = eeLearner();
    $enroll = app(EnrollInFreeCourse::class);

    expect(eeErrors(fn () => $enroll($learner, $learner, eeCourse('Paid draft', 'paid', 'draft')))['course'][0] ?? null)
        ->toBe(__('entitlements.not_free'))
        ->and(eeErrors(fn () => $enroll($learner, $learner, eeCourse('Free draft', 'free', 'draft')))['course'][0] ?? null)
        ->toBe(__('entitlements.not_open'));
});

it('refuses a verified parent enrolling an inactive child', function (): void {
    $course = eeCourse('Child');
    $parent = User::factory()->create()->assignRole('parent');
    $child = User::factory()->create(['status' => 'suspended'])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->getKey(), 'child_id' => $child->getKey(), 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);

    expect(fn () => app(EnrollInFreeCourse::class)($parent, $child, $course))->toThrow(AuthorizationException::class)
        ->and(Entitlement::query()->count())->toBe(0);
});

it('counts any grant, even revoked, as course history that blocks deletion', function (): void {
    $course = eeCourse('History');
    $admin = User::factory()->create()->assignRole('admin');
    $history = app(EntitlementHistory::class);

    expect($history->blocks($course))->toBeNull();

    $entitlement = app(GrantEntitlement::class)(eeLearner(), $course, EntitlementSource::Free);
    expect($history->blocks($course))->toBe('entitlements');

    app(RevokeEntitlement::class)($admin, $entitlement, 'refund');
    expect($history->blocks($course))->toBe('entitlements')
        ->and($entitlement->refresh()->status)->toBe(Entitlement::REVOKED);
});

it('keeps a scheduled manual grant inactive until its chosen start date', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $learner = eeLearner();
    $course = eeCourse();
    $start = now()->addDays(2)->startOfDay();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin)->withoutVite();

    Livewire::test(ListManualGrants::class)
        ->callAction('grant', [
            'learner_id' => $learner->id, 'course_id' => $course->id, 'starts_at' => $start->toDateString(),
            'lifetime' => false, 'ends_at' => $start->copy()->addWeek()->toDateString(), 'reason' => 'Test scholarship',
        ])->assertHasNoActionErrors();

    $grant = Entitlement::query()->where('learner_id', $learner->id)->sole();
    expect($grant->starts_at->equalTo($start))->toBeTrue()
        ->and(CourseAccessSummary::for($course->id)['active'])->toBe(0)
        ->and(ManualGrantResource::state($grant))->toBe('scheduled');

    $this->travelTo($start);
    expect(CourseAccessSummary::for($course->id)['active'])->toBe(1)
        ->and(ManualGrantResource::state($grant))->toBe('active');
})->group('T-P4-02', 'A2');
