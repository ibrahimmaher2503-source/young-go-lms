<?php

// SEC-9 | Medium: view-as eligibility is re-checked on every redemption of an open session | T-P9-10, T-P11-01

declare(strict_types=1);

use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Support\Actions\StartViewAs;
use App\Domain\Support\ViewAsState;
use App\Models\AiSafeguardingFlag;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

function sec9Reason(): string
{
    return 'Learner asked for help with the lesson access.';
}

/** Starts a valid view-as session for an eligible learner and proves the home page opens before anything changes. */
function sec9OpenSession(): array
{
    $support = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('support');
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    $session = app(StartViewAs::class)($support, $learner, sec9Reason());
    test()->actingAs($support);
    test()->get(route('support.view-as.home'))->assertOk();

    $course = Course::query()->create([
        'title' => ['en' => 'SEC-9 course'], 'slug_ar' => 'sec9-ar-'.uniqid(), 'slug_en' => 'sec9-en-'.uniqid(), 'status' => 'published', 'access_mode' => 'free',
    ]);

    return compact('support', 'learner', 'session', 'course');
}

/** Applies one mid-session change that must end the open session on the next request. */
function sec9Change(string $cause, User $support, User $learner): void
{
    match ($cause) {
        'support.view_as permission revoked' => (function () use ($support): void {
            Role::findByName('support')->revokePermissionTo('support.view_as');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $support->refresh();
        })(),
        'open safeguarding flag' => AiSafeguardingFlag::query()->create([
            'student_id' => $learner->id, 'conversation_id' => null, 'category' => 'self_harm', 'status' => 'open',
        ]),
        'deletion request requested' => DB::table('data_requests')->insert([
            'subject_id' => $learner->id, 'requested_by' => $learner->id, 'type' => 'deletion', 'status' => 'requested',
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'learner suspended' => $learner->forceFill(['status' => 'suspended'])->save(),
    };
}

it('ends an open view-as session on the next request when its eligibility is revoked mid-session', function (string $cause): void {
    ['support' => $support, 'learner' => $learner, 'session' => $session, 'course' => $course] = sec9OpenSession();

    sec9Change($cause, $support, $learner);

    test()->get(route('support.view-as.home'))->assertForbidden();
    test()->get(route('support.view-as.course', $course))->assertForbidden();

    $session->refresh();
    expect($session->ended_at)->not->toBeNull()
        ->and($session->end_cause)->toBe(ViewAsSession::END_REVOKED)
        ->and(session(ViewAsState::SESSION_KEY))->toBeNull();

    $stopped = Activity::query()->where('event', 'support.view_as_stopped')->get();
    expect($stopped)->toHaveCount(1)
        ->and(json_encode($stopped->first()->properties))->toContain('"cause":"revoked"');
})->with([
    'support.view_as permission revoked',
    'open safeguarding flag',
    'deletion request requested',
    'learner suspended',
])->group('T-P9-10', 'T-P11-01');
