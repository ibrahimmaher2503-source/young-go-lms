<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-015 | /family verification states (EN/AR), JSON contract and throttle of the guardian code routes

use App\Domain\Guardians\Actions\ConfirmGuardianOtp;
use App\Domain\Guardians\Actions\SendGuardianOtp;
use App\Domain\Guardians\Notifications\GuardianOtpNotice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** An active parent; the email is verified unless the test says otherwise. */
function dfvParent(bool $verifiedEmail = true): User
{
    return User::factory()->create([
        'status' => 'active',
        'password' => 'Password1!secure',
        'email_verified_at' => $verifiedEmail ? now() : null,
    ])->assignRole('parent');
}

/** The 6-digit code the notice carries (only its hash is stored). */
function dfvSentCode(User $parent): string
{
    $code = null;
    Notification::assertSentTo($parent, GuardianOtpNotice::class, function (GuardianOtpNotice $notice) use (&$code): bool {
        $code = (new ReflectionProperty(GuardianOtpNotice::class, 'code'))->getValue($notice);

        return true;
    });

    return (string) $code;
}

// ---------------------------------------------------------------- /family states

it('asks an unverified parent to verify the email and offers no code form on the family page', function (): void {
    $parent = dfvParent(false);

    $this->actingAs($parent)->get('/family')->assertOk()
        ->assertSee(__('pages.verify_before_child'))
        ->assertDontSee(__('guardian.verification.send'))
        ->assertDontSee('id="guardian_code"', false);
});

it('shows the send-code action to a verified parent with no pending code, in EN and AR', function (string $locale): void {
    $parent = dfvParent();
    $label = trans('guardian.verification.send', [], $locale);

    $this->actingAs($parent)->withSession(['locale' => $locale])->get('/family')->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($label)
        ->assertSee('guardian/verification/code')
        ->assertDontSee('id="guardian_code"', false)
        ->assertDontSee(trans('guardian.verification.resend', [], $locale));
})->with(['en', 'ar'])->group('T-DECISIONS-2', 'O-015');

it('shows the code field and the resend label once a code is pending', function (): void {
    Notification::fake();
    $parent = dfvParent();
    app(SendGuardianOtp::class)->handle($parent);

    $this->actingAs($parent)->get('/family')->assertOk()
        ->assertSee(__('guardian.verification.resend'))
        ->assertSee('id="guardian_code"', false)
        ->assertSee('maxlength="6"', false)
        ->assertSee('guardian/verification')
        ->assertSee(__('guardian.verification.confirm'));
})->group('T-DECISIONS-2', 'O-015');

it('shows the verified state and no send or code form once the guardian is verified', function (): void {
    Notification::fake();
    $parent = dfvParent();
    app(SendGuardianOtp::class)->handle($parent);
    app(ConfirmGuardianOtp::class)->handle($parent, dfvSentCode($parent));

    $this->actingAs($parent)->get('/family')->assertOk()
        ->assertSee(__('guardian.verification.verified'))
        ->assertSee(__('guardian.verification.done'))
        ->assertDontSee('id="guardian_code"', false)
        ->assertDontSee('guardian/verification/code');
})->group('T-DECISIONS-2', 'O-015');

// ---------------------------------------------------------------- JSON contract

it('answers the send route with sent true and no-store, and refuses a second code inside the 60 second gap with a code error key', function (): void {
    Notification::fake();
    $parent = dfvParent();

    $this->actingAs($parent)->postJson('/guardian/verification/code')
        ->assertOk()
        ->assertJson(['sent' => true]);
    expect((string) $this->actingAs($parent)->get('/family')->headers->get('Cache-Control'))->not->toBe('');

    $this->actingAs($parent)->postJson('/guardian/verification/code')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
})->group('T-DECISIONS-2', 'O-015');

it('answers the confirm route with verified true and no-store on the right code, and a code error key on a wrong one', function (): void {
    Notification::fake();
    $parent = dfvParent();
    $this->actingAs($parent)->postJson('/guardian/verification/code')->assertOk();
    $code = dfvSentCode($parent);

    $this->actingAs($parent)->postJson('/guardian/verification', ['code' => $code === '000000' ? '111111' : '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->actingAs($parent)->postJson('/guardian/verification', ['code' => $code])
        ->assertOk()
        ->assertJson(['verified' => true])
        ->assertHeaderMissing('X-Missing-Marker')
        ->assertHeader('Cache-Control');
})->group('T-DECISIONS-2', 'O-015');

it('refuses the code routes to a learner who is not a parent', function (): void {
    $student = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');

    $this->actingAs($student)->postJson('/guardian/verification/code')->assertForbidden();
})->group('T-DECISIONS-2', 'O-015');

it('throttles the send route after six requests a minute', function (): void {
    Notification::fake();
    $parent = dfvParent();

    for ($i = 0; $i < 6; $i++) {
        expect($this->actingAs($parent)->postJson('/guardian/verification/code')->status())->not->toBe(429);
    }

    $this->actingAs($parent)->postJson('/guardian/verification/code')->assertStatus(429);
})->group('T-DECISIONS-2', 'O-015');
