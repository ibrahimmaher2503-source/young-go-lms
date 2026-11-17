<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Actions\IssueLinkCode;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function linkUser(string $role): User
{
    $user = User::factory()->create(['password' => 'Password1!secure']);
    $user->assignRole($role);

    return $user;
}

it('issues a code to a learner and links it as pending on redeem', function (): void {
    $learner = linkUser('student');
    $parent = linkUser('parent');

    $code = $this->actingAs($learner)->postJson('/guardian/link-codes')->assertCreated()->json('code');
    expect($code)->toBeString()->toHaveLength(8)
        ->and(DB::table('guardian_link_codes')->value('code_hash'))->not->toBe($code);

    $this->flushSession();
    $this->actingAs($parent)->postJson('/guardian/links', ['code' => strtolower($code)])->assertCreated();

    $link = GuardianChildLink::query()->sole();
    expect($link->status)->toBe('pending')
        ->and($link->method)->toBe('link_code')
        ->and($link->guardian_id)->toBe($parent->id)
        ->and($link->child_id)->toBe($learner->id);
})->group('T-P1-06', 'A1', 'YG-GAP-FAM-002');

it('makes link codes single-use', function (): void {
    $code = app(IssueLinkCode::class)->handle(linkUser('student'));
    app(RedeemLinkCode::class)->handle(linkUser('parent'), $code);

    expect(fn () => app(RedeemLinkCode::class)->handle(linkUser('parent'), $code))
        ->toThrow(ValidationException::class);
    expect(GuardianChildLink::query()->count())->toBe(1);
})->group('T-P1-06', 'A1', 'YG-GAP-FAM-002');

it('expires link codes after 48 hours', function (): void {
    $code = app(IssueLinkCode::class)->handle(linkUser('student'));
    $this->travel(48)->hours();
    $this->travel(1)->seconds();

    expect(fn () => app(RedeemLinkCode::class)->handle(linkUser('parent'), $code))
        ->toThrow(ValidationException::class);
})->group('T-P1-06', 'A1', 'YG-GAP-FAM-002');

it('replaces an earlier unused code when a new one is issued', function (): void {
    $learner = linkUser('student');
    $first = app(IssueLinkCode::class)->handle($learner);
    app(IssueLinkCode::class)->handle($learner);

    expect(fn () => app(RedeemLinkCode::class)->handle(linkUser('parent'), $first))
        ->toThrow(ValidationException::class);
})->group('T-P1-06', 'A1');

it('allows only learners to issue and only parents to redeem', function (): void {
    $this->actingAs(linkUser('parent'))->postJson('/guardian/link-codes')->assertForbidden();
    $code = app(IssueLinkCode::class)->handle(linkUser('student'));
    $this->flushSession();
    $this->actingAs(linkUser('super_admin'))->postJson('/guardian/links', ['code' => $code])->assertForbidden();
    expect(GuardianChildLink::query()->count())->toBe(0);
})->group('T-P1-06', 'A1', 'D-006');

it('keeps the link inactive until the learner confirms it', function (): void {
    $learner = linkUser('student');
    $parent = linkUser('parent');
    app(RedeemLinkCode::class)->handle($parent, app(IssueLinkCode::class)->handle($learner));
    $link = GuardianChildLink::query()->sole();

    expect(Gate::forUser($parent)->inspect('guardian.child.access', $learner)->status())->toBe(404);

    $this->actingAs($parent)->postJson("/guardian/links/{$link->id}/confirm")->assertNotFound();
    $this->flushSession();
    $this->actingAs(linkUser('student'))->postJson("/guardian/links/{$link->id}/confirm")->assertNotFound();
    $this->flushSession();
    $this->actingAs($learner)->postJson("/guardian/links/{$link->id}/confirm")->assertNoContent();

    expect($link->refresh()->status)->toBe('verified')
        ->and($link->getAttribute('verified_at'))->not->toBeNull()
        ->and(Gate::forUser($parent)->allows('guardian.child.access', $learner))->toBeTrue();
})->group('T-P1-06', 'A2', 'YG-GAP-FAM-002');

it('requires password confirmation to enter a child context', function (): void {
    $parent = linkUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'ctx-child', 'password' => 'ChildPassword1!', 'name_en' => 'Kid', 'birth_year' => 2015, 'birth_month' => 1,
    ]);

    $this->actingAs($parent)->postJson("/guardian/context/{$child->id}")->assertStatus(423);
    expect(session('acting_child_id'))->toBeNull();

    $this->postJson('/confirm-password', ['password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/confirm-password', ['password' => 'Password1!secure'])->assertNoContent();
    $this->postJson("/guardian/context/{$child->id}")->assertNoContent();

    expect(session('acting_child_id'))->toBe($child->id)
        ->and(app(ActingLearner::class)->current()?->id)->toBe($child->id);
})->group('T-P1-06', 'A3', 'YG-GAP-FAM-004');

it('rejects entering the context of an unlinked learner with 404', function (): void {
    $parent = linkUser('parent');
    $stranger = linkUser('student');

    $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()])
        ->postJson("/guardian/context/{$stranger->id}")->assertNotFound();
    expect(session('acting_child_id'))->toBeNull();
})->group('T-P1-06', 'A3', 'I02');

it('audits actions in child context with the parent as actor', function (): void {
    $parent = linkUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'audit-child', 'password' => 'ChildPassword1!', 'name_en' => 'Kid', 'birth_year' => 2015, 'birth_month' => 1,
    ]);

    $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()])
        ->postJson("/guardian/context/{$child->id}")->assertNoContent();

    $entered = Activity::query()->where('event', 'guardian.context_entered')->sole();
    expect($entered->causer_id)->toBe($parent->id)->and($entered->subject_id)->toBe($child->id);

    // Any audited action while acting carries the acting parent.
    app(AuditLogger::class)->record('learner.example_action', $child, $parent->id);
    $action = Activity::query()->where('event', 'learner.example_action')->sole();
    expect($action->causer_id)->toBe($parent->id)
        ->and($action->subject_id)->toBe($child->id)
        ->and($action->properties->get('context')['acting_for'])->toBe(['type' => User::class, 'id' => $child->id]);

    $this->deleteJson('/guardian/context')->assertNoContent();
    expect(session('acting_child_id'))->toBeNull()
        ->and(Activity::query()->where('event', 'guardian.context_exited')->sole()->causer_id)->toBe($parent->id)
        ->and(app(ActingLearner::class)->current()?->id)->toBe($parent->id);
})->group('T-P1-06', 'A4', 'YG-GAP-FAM-004', 'PRD-22');

it('drops a stale child context when the link is no longer verified', function (): void {
    $parent = linkUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'stale-child', 'password' => 'ChildPassword1!', 'name_en' => 'Kid', 'birth_year' => 2015, 'birth_month' => 1,
    ]);
    GuardianChildLink::query()->update(['status' => 'revoked']);

    $this->actingAs($parent)->withSession(['acting_child_id' => $child->id]);
    $acting = app(ActingLearner::class);

    expect($acting->current()?->id)->toBe($parent->id)
        ->and($acting->isParentContext())->toBeFalse()
        ->and(session('acting_child_id'))->toBeNull();
})->group('T-P1-06', 'A4', 'I02');

it('marks parent context so private child AI conversations stay hidden', function (): void {
    $parent = linkUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'ai-child', 'password' => 'ChildPassword1!', 'name_en' => 'Kid', 'birth_year' => 2015, 'birth_month' => 1,
    ]);
    $this->actingAs($parent)->withSession(['acting_child_id' => $child->id]);

    expect(app(ActingLearner::class)->isParentContext())->toBeTrue()
        ->and(app(ActingLearner::class)->canViewPrivateLearnerConversations())->toBeFalse();
})->group('T-P1-06', 'O-016');
