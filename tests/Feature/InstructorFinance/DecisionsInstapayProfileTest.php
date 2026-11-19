<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-012 | A2

use App\Domain\Instructors\Actions\RevealPayoutProfile;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\PayoutMask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function dipInstructor(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
}

function dipSave(User $instructor, string $address): PayoutProfile
{
    return app(SavePayoutProfile::class)($instructor, [
        'method' => 'instapay', 'holder_name' => 'Instapay Holder', 'instapay_address' => $address,
    ]);
}

function dipInstapayRefusal(User $instructor, string $address): ?string
{
    try {
        dipSave($instructor, $address);
    } catch (ValidationException $exception) {
        return (string) ($exception->errors()['instapay_address'][0] ?? '');
    }

    return null;
}

// ---------------------------------------------------------------- A2: instapay profile

it('saves an instapay name@bank address and a valid mobile number, lower-cased and without spaces', function (): void {
    $instructor = dipInstructor();

    $profile = dipSave($instructor, ' Name@Instapay ');
    expect($profile->method)->toBe('instapay')
        ->and($profile->details['instapay_address'])->toBe('name@instapay');

    $other = dipInstructor();
    expect(dipSave($other, '+201001234567')->details['instapay_address'])->toBe('+201001234567');
})->group('T-DECISIONS-2', 'O-012', 'A2');

it('refuses an invalid Instapay address, an empty value and spaces only, and changes no row', function (): void {
    $instructor = dipInstructor();
    $expected = __('instructor_onboarding.payout.errors.instapay_invalid');

    expect(dipInstapayRefusal($instructor, 'a@b'))->toBe($expected)
        ->and(dipInstapayRefusal($instructor, ''))->toBe($expected)
        ->and(dipInstapayRefusal($instructor, '   '))->toBe($expected)
        ->and(PayoutProfile::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'like', 'payout_profile.%')->count())->toBe(0);
})->group('T-DECISIONS-2', 'O-012', 'A2');

it('stores the Instapay address encrypted, audits only the method and never the address', function (): void {
    $instructor = dipInstructor();
    dipSave($instructor, 'secret.name@instapay');

    $raw = (string) DB::table('instructor_payout_profiles')->where('user_id', $instructor->id)->value('details');
    $audit = json_encode(Activity::query()->where('event', 'payout_profile.created')->get()->pluck('properties')->all());

    expect($raw)->not->toContain('secret.name')
        ->and($raw)->not->toContain('instapay')
        ->and($audit)->toContain('instapay')
        ->and($audit)->not->toContain('secret.name');
})->group('T-DECISIONS-2', 'O-012', 'A2');

it('masks the Instapay address to its last four characters and never shows the full address', function (): void {
    $profile = dipSave(dipInstructor(), 'name@instapay');

    $masked = PayoutMask::identifier($profile);

    expect($masked)->toEndWith('apay')
        ->and($masked)->not->toContain('name')
        ->and($masked)->not->toContain('@')
        ->and($masked)->not->toBe('name@instapay');
})->group('T-DECISIONS-2', 'O-012', 'A2');

it('shows finance the localized Instapay method and only the masked address in the payout profile list', function (string $locale, string $method): void {
    $this->withoutVite();
    dipSave(dipInstructor(), 'name@instapay');
    $finance = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('finance');

    $this->actingAs($finance)->get('/finance/payout-profiles')
        ->assertOk()
        ->assertSee($method)
        ->assertSee('*********apay')
        ->assertDontSee('name@instapay')
        ->assertDontSee('Instapay Holder');
})->with([
    'Arabic' => ['ar', 'إنستاباي'],
    'English' => ['en', 'Instapay'],
])->group('T-DECISIONS-2', 'O-012', 'YG-GAP-INS-003', 'A2');

it('reveals the Instapay address only to a user with payout_profile.reveal and audits the reveal without values', function (): void {
    $profile = dipSave(dipInstructor(), 'name@instapay');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');

    $details = app(RevealPayoutProfile::class)($finance, $profile);

    $audit = Activity::query()->where('event', 'payout_profile.revealed')->sole();
    expect($details['instapay_address'])->toBe('name@instapay')
        ->and(json_encode($audit->properties))->not->toContain('name@instapay')
        ->and(json_encode($audit->properties))->toContain('instapay');
})->group('T-DECISIONS-2', 'O-012', 'A2');

it('refuses the reveal to support, a student and the instructor themselves', function (string $role): void {
    $profile = dipSave(dipInstructor(), 'name@instapay');
    $actor = User::factory()->create(['status' => 'active'])->assignRole($role);

    expect(fn () => app(RevealPayoutProfile::class)($actor, $profile))->toThrow(AuthorizationException::class);
    expect(Activity::query()->where('event', 'payout_profile.revealed')->count())->toBe(0);
})->with(['support', 'student', 'instructor_academy'])->group('T-DECISIONS-2', 'O-012', 'A2');
