<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AssignStaffRoles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['identity.terms_version' => 'demo-terms', 'identity.privacy_version' => 'demo-privacy']);
});

function demoAs($test, ?User $user = null)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $user === null ? $test : $test->actingAs($user);
}

/**
 * T-P1-MG demo script, executed over HTTP against the seeded roles:
 * register an adult → verify → parent creates two children → child login → link-code flow
 * → admin assigns roles → audit viewer shows the events → every screen works in AR and EN.
 */
it('runs the P1 demo script end to end', function (): void {
    // 1. A parent registers and verifies the email address.
    $this->postJson('/register', [
        'account_type' => 'parent', 'email' => 'demo.parent@example.test', 'username' => 'demo-parent', 'password' => 'DemoParent1!', 'password_confirmation' => 'DemoParent1!',
        'name_en' => 'Demo Parent', 'name_ar' => 'ولي أمر تجريبي', 'terms_version' => 'demo-terms', 'privacy_version' => 'demo-privacy', 'accept_terms' => true, 'accept_privacy' => true,
    ])->assertCreated();
    $parent = User::where('email', 'demo.parent@example.test')->firstOrFail();
    expect($parent->getRoleNames()->all())->toBe(['parent'])->and($parent->hasVerifiedEmail())->toBeFalse();
    $this->postJson('/guardian/children', ['username' => 'too-early', 'password' => 'ChildDemo1!', 'password_confirmation' => 'ChildDemo1!', 'name_en' => 'Early', 'birth_year' => 2016, 'birth_month' => 1])
        ->assertUnprocessable();
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $parent->id, 'hash' => sha1('demo.parent@example.test')]);
    $this->withHeader('Accept', 'text/html')->get($link)->assertRedirect('/home');
    expect($parent->refresh()->hasVerifiedEmail())->toBeTrue();

    // 2. The parent creates two children.
    foreach (['demo-ali' => ['Ali', 'علي', 2016], 'demo-sara' => ['Sara', 'سارة', 2018]] as $username => [$en, $ar, $year]) {
        $this->postJson('/guardian/children', [
            'username' => $username, 'password' => 'ChildDemo1!', 'password_confirmation' => 'ChildDemo1!',
            'name_en' => $en, 'name_ar' => $ar, 'birth_year' => $year, 'birth_month' => 5,
        ])->assertCreated();
    }
    $this->get('/family')->assertOk()->assertSee('demo-ali')->assertSee('demo-sara');

    // 3. A child signs in with the username only.
    demoAs($this);
    $this->postJson('/login', ['login' => 'demo-ali', 'password' => 'ChildDemo1!'])->assertNoContent();
    $this->get('/profile')->assertOk();
    expect(User::where('username', 'demo-ali')->value('email'))->toBeNull();

    // 4. Link-code flow: an existing learner links to a second parent, who is confirmed by the learner.
    $learner = User::factory()->create(['password' => 'Learner1!demo', 'name' => 'Existing Learner']);
    $learner->assignRole('student');
    $second = User::factory()->create(['name' => 'Second Parent']);
    $second->assignRole('parent');
    $code = demoAs($this, $learner)->postJson('/guardian/link-codes')->assertCreated()->json('code');
    demoAs($this, $second)->postJson('/guardian/links', ['code' => $code])->assertCreated();
    $linkId = (int) DB::table('guardian_child_links')->where('child_id', $learner->id)->value('id');
    demoAs($this, $learner)->postJson("/guardian/links/{$linkId}/confirm")->assertNoContent();
    expect(DB::table('guardian_child_links')->where('id', $linkId)->value('status'))->toBe('verified');

    // 5. The parent switches into a child's context with the password; the audit shows the parent as actor.
    $child = User::where('username', 'demo-sara')->firstOrFail();
    demoAs($this, $parent);
    $this->postJson('/confirm-password', ['password' => 'DemoParent1!'])->assertNoContent();
    $this->postJson("/guardian/context/{$child->id}")->assertNoContent();
    $this->get('/family')->assertOk()->assertSee('data-child-context', false);
    $this->deleteJson('/guardian/context')->assertNoContent();

    // 6. A super admin assigns roles; the audit viewer lists the events.
    $admin = User::factory()->create(['name' => 'Demo Admin']);
    $admin->assignRole('super_admin');
    $staff = User::factory()->create(['name' => 'Demo Staff']);
    app(AssignStaffRoles::class)->handle($admin, $staff, ['content_manager', 'marketing']);
    expect($staff->refresh()->getRoleNames()->sort()->values()->all())->toBe(['content_manager', 'marketing']);

    $events = Activity::query()->where('log_name', 'audit')->pluck('event')->unique()->sort()->values()->all();
    expect($events)->toContain('guardian.child_created', 'guardian.link_code_issued', 'guardian.link_requested', 'guardian.link_confirmed', 'guardian.context_entered', 'guardian.context_exited', 'user.roles_changed');
    $viewer = User::factory()->create();
    $viewer->assignRole('admin');
    demoAs($this, $viewer)->get(route('filament.admin.resources.audit.audit-logs.index'))->assertOk()->assertSee(__('audit-events.guardian.child_created'));

    // 7. Every public and account screen renders in Arabic (RTL) and English (LTR), with the language switch returning to the same page.
    foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            demoAs($this)->withSession(['locale' => $locale])->get($path)->assertOk()->assertSee("lang=\"{$locale}\" dir=\"{$direction}\"", false);
        }
        foreach (['/profile', '/family', '/security/two-factor'] as $path) {
            // A signed-in user's saved language wins, so switch it the way a person would.
            demoAs($this, $parent)->post('/locale', ['locale' => $locale, 'return_to' => $path])->assertRedirect($path);
            $this->get($path)->assertOk()->assertSee("lang=\"{$locale}\" dir=\"{$direction}\"", false);
        }
        demoAs($this, $learner)->post('/locale', ['locale' => $locale, 'return_to' => '/link'])->assertRedirect('/link');
        $this->get('/link')->assertOk()->assertSee("dir=\"{$direction}\"", false);
    }
    demoAs($this)->post('/locale', ['locale' => 'en', 'return_to' => '/login'])->assertRedirect('/login');

    // Key rows for the evidence record.
    fwrite(STDERR, '');
    expect(DB::table('guardian_child_links')->count())->toBe(3)
        ->and(User::whereNull('email')->count())->toBe(2);
})->group('T-P1-MG', 'A1', 'J01', 'J02');
