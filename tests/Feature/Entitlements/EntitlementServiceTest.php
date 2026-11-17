<?php

declare(strict_types=1);

use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\UnavailableReason;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\ExpireEntitlements;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Settings\PlatformFlag;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: Course, 1: CurriculumItem, 2: User, 3: User} a published course with one published lesson, a learner and an admin */
function entBuilt(array $itemAttributes = [], string $status = 'published'): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Ent'], 'slug_ar' => 'e-'.uniqid(), 'slug_en' => 'e-'.uniqid(), 'status' => $status]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L'], 'type' => 'video']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $item->refresh()->update($itemAttributes);

    return [$course->refresh(), $item->refresh(), User::factory()->create()->assignRole('student'), User::factory()->create()->assignRole('admin')];
}

function entGrant(User $learner, Course $course, array $overrides = []): Entitlement
{
    return app(GrantEntitlement::class)(
        $learner, $course, $overrides['source'] ?? EntitlementSource::Free, $overrides['ref'] ?? '',
        $overrides['starts'] ?? now()->subDay(), $overrides['ends'] ?? null,
    );
}

it('creates one row for the same source however many times it is granted', function (): void {
    [$course, , $learner] = entBuilt();

    $first = entGrant($learner, $course, ['source' => EntitlementSource::Purchase, 'ref' => 'order-1']);
    $again = entGrant($learner, $course, ['source' => EntitlementSource::Purchase, 'ref' => 'order-1']);
    entGrant($learner, $course, ['source' => EntitlementSource::Purchase, 'ref' => 'order-2']);
    entGrant($learner, $course, ['source' => EntitlementSource::Free]);

    expect($again->id)->toBe($first->id)->and(Entitlement::query()->count())->toBe(3)
        ->and(Activity::query()->where('event', 'entitlement.granted')->count())->toBe(3);
})->group('T-P4-01', 'A1');

it('allows an entitled learner into a published lesson and explains every refusal', function (array $setup, string $reason): void {
    [$course, $item, $learner] = entBuilt($setup['item'] ?? [], $setup['status'] ?? 'published');
    if ($setup['section_unpublished'] ?? false) {
        $item->section->update(['published' => false]);
    }
    if (($setup['entitle'] ?? true) !== false) {
        entGrant($learner, $course, ['starts' => $setup['starts'] ?? now()->subDay(), 'ends' => $setup['ends'] ?? null]);
    }
    if ($setup['archive'] ?? false) {
        $item->itemable->update(['archived_at' => now()]);
    }

    $decision = app(EntitlementService::class)->canAccessItem($learner, $item->refresh());

    expect($decision->allowed)->toBeFalse()->and($decision->reason)->toBe($reason)->and($decision->message())->not->toBeEmpty();
})->with([
    'course in draft' => [['status' => 'draft'], 'course_unpublished'],
    'course suspended' => [['status' => 'suspended'], 'course_unpublished'],
    'item unpublished' => [['item' => ['status' => 'draft']], 'item_unpublished'],
    'section unpublished' => [['section_unpublished' => true], 'item_unpublished'],
    'lesson archived' => [['archive' => true], 'item_unpublished'],
    'no entitlement' => [['entitle' => false], 'not_entitled'],
    'entitlement ended' => [['starts' => now()->subDays(10), 'ends' => now()->subDay()], 'entitlement_expired'],
    'entitlement not started' => [['starts' => now()->addDay()], 'entitlement_expired'],
    'unlock date ahead' => [['item' => ['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]], 'drip_locked'],
    'unlock days not reached' => [['item' => ['unlock_rule' => 'days', 'unlock_days' => 30]], 'drip_locked'],
    'unknown unlock rule' => [['item' => ['unlock_rule' => 'mystery']], 'drip_locked'],
])->group('T-P4-01', 'A2');

it('lets an entitled learner in and opens dated and day-based lessons when their time comes', function (): void {
    [$course, $item, $learner] = entBuilt(['unlock_rule' => 'date', 'unlock_at' => now()->subHour()]);
    entGrant($learner, $course);
    $service = app(EntitlementService::class);

    expect($service->canAccessItem($learner, $item)->allowed)->toBeTrue()->and($service->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and($service->canAccessLessonFiles($learner, $item->itemable))->toBeTrue();

    $item->update(['unlock_rule' => 'days', 'unlock_days' => 0]);
    expect($service->canAccessItem($learner, $item->refresh())->allowed)->toBeTrue();

    $item->update(['unlock_rule' => 'date', 'unlock_at' => now()->addDay()]);
    $decision = $service->canAccessItem($learner, $item->refresh());
    expect($decision->unlockAt?->isFuture())->toBeTrue();
})->group('T-P4-01', 'A2');

it('opens a free preview lesson without an entitlement but never in a closed course', function (): void {
    [$course, $item, $learner] = entBuilt();
    $item->itemable->update(['is_preview' => true]);
    $service = app(EntitlementService::class);

    expect($service->canAccessItem($learner, $item->refresh())->allowed)->toBeTrue()
        ->and($service->canAccessCourse($learner, $course)->reason)->toBe('not_entitled');

    $course->update(['status' => 'draft']);
    expect($service->canAccessItem($learner, $item->refresh()->load('course'))->reason)->toBe('course_unpublished');
})->group('T-P4-01', 'A2');

it('denies expired and revoked entitlements for lessons, files and the AI assistant', function (): void {
    [$course, $item, $learner, $admin] = entBuilt();
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();
    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'x.pdf', 'sha256' => str_repeat('a', 64), 'size' => 1, 'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    config(['ai.adult_age' => 18]);
    $learner->forceFill(['birth_year' => now()->year - 30, 'birth_month' => 1])->save();
    $service = app(EntitlementService::class);
    $eligibility = fn () => app(AssistantEligibility::class)->check($learner->refresh(), $lesson->refresh());

    $entitlement = entGrant($learner, $course, ['ends' => now()->addDay()]);
    expect($service->canAccessLessonFiles($learner, $lesson))->toBeTrue()->and($eligibility()->isAvailable())->toBeTrue();

    $this->travelTo(now()->addDays(2));
    $service->flush();
    expect($service->canAccessLessonFiles($learner, $lesson))->toBeFalse()->and($eligibility()->reason)->toBe(UnavailableReason::NoEntitlement);
    $this->travelBack();

    $entitlement->update(['ends_at' => null]);
    $service->flush();
    expect($eligibility()->isAvailable())->toBeTrue();

    app(RevokeEntitlement::class)($admin, $entitlement, 'refund approved');
    expect($service->canAccessLessonFiles($learner, $lesson))->toBeFalse()->and($eligibility()->reason)->toBe(UnavailableReason::NoEntitlement)
        ->and($entitlement->refresh()->status)->toBe(Entitlement::REVOKED)->and($entitlement->revoked_by)->toBe($admin->id);
})->group('T-P4-01', 'A2');

it('drops the request cache the moment access is granted or revoked', function (): void {
    [$course, $item, $learner, $admin] = entBuilt();
    $service = app(EntitlementService::class);

    expect($service->canAccessItem($learner, $item)->allowed)->toBeFalse();
    $entitlement = entGrant($learner, $course);
    expect($service->canAccessItem($learner, $item)->allowed)->toBeTrue();
    app(RevokeEntitlement::class)($admin, $entitlement, 'wrong learner');
    expect($service->canAccessItem($learner, $item)->allowed)->toBeFalse();
})->group('T-P4-01', 'A2');

it('denies inactive learners even with a current entitlement or a preview lesson', function (string $status, bool $preview): void {
    [$course, $item, $learner] = entBuilt();
    entGrant($learner, $course);
    $learner->forceFill(['status' => $status])->save();
    $item->itemable->update(['is_preview' => $preview]);

    $access = app(EntitlementService::class);
    expect($access->canAccessItem($learner, $item->refresh())->reason)->toBe('not_entitled')
        ->and($access->canAccessLessonFiles($learner, $item->itemable))->toBeFalse();
})->with(['suspended', 'deactivated'])->with([true, false])->group('T-P4-01', 'A2', 'I01');

it('lets staff grant access by hand without any payment (I05) and refuses everyone else', function (): void {
    [$course, , $learner, $admin] = entBuilt();
    $grant = app(GrantEntitlement::class);

    $entitlement = $grant($learner, $course, EntitlementSource::ManualGrant, 'support-ticket-7', null, now()->addMonth(), $admin, 'scholarship');

    expect($entitlement->status)->toBe(Entitlement::ACTIVE)->and($entitlement->granted_by)->toBe($admin->id)->and($entitlement->reason)->toBe('scholarship')
        ->and(Schema::hasTable('orders'))->toBeFalse() // no payment exists, none was needed
        ->and(fn () => $grant($learner, $course, EntitlementSource::ManualGrant, 'x', null, null, $learner))->toThrow(AuthorizationException::class)
        ->and(fn () => $grant($learner, $course, EntitlementSource::ManualPayment, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => $grant($learner, $course, EntitlementSource::Free, '', now(), now()->subDay()))->toThrow(ValidationException::class);
})->group('T-P4-01', 'A1', 'I05');

it('refuses to revoke without permission or a reason', function (): void {
    [$course, , $learner, $admin] = entBuilt();
    $entitlement = entGrant($learner, $course);

    expect(fn () => app(RevokeEntitlement::class)($learner, $entitlement, 'because'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RevokeEntitlement::class)($admin, $entitlement, ' '))->toThrow(ValidationException::class);
    expect(app(RevokeEntitlement::class)($admin, $entitlement, 'duplicate')->status)->toBe(Entitlement::REVOKED);
    expect(app(RevokeEntitlement::class)($admin, $entitlement, 'again')->status)->toBe(Entitlement::REVOKED)
        ->and(Activity::query()->where('event', 'entitlement.revoked')->count())->toBe(1);
})->group('T-P4-01', 'A2');

it('expires finished entitlements on the schedule and leaves the rest alone', function (): void {
    [$course, , $learner] = entBuilt();
    $other = User::factory()->create();
    $ended = entGrant($learner, $course, ['starts' => now()->subDays(3), 'ends' => now()->subDay()]);
    $open = entGrant($other, $course, ['ends' => now()->addDay()]);
    $forever = entGrant(User::factory()->create(), $course);

    expect(app(ExpireEntitlements::class)())->toBe(1)->and($ended->refresh()->status)->toBe(Entitlement::EXPIRED)
        ->and($open->refresh()->status)->toBe(Entitlement::ACTIVE)->and($forever->refresh()->status)->toBe(Entitlement::ACTIVE)
        ->and(app(ExpireEntitlements::class)())->toBe(0);

    $this->artisan('entitlements:expire')->expectsOutputToContain('Expired 0')->assertSuccessful();
    expect(collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'entitlements:expire')))->toBeTrue();
})->group('T-P4-01', 'A2');

it('is the only checker bound for lesson access', function (): void {
    expect(app(LessonAccessChecker::class))->toBeInstanceOf(EntitlementService::class);
})->group('T-P4-01', 'A2');

it('keeps the invariant scaffolds for commerce sources that arrive in P8', function (): void {})->todo()->group('T-P4-01', 'I03', 'I04');
