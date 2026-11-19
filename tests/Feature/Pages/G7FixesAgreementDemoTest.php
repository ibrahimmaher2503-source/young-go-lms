<?php

declare(strict_types=1);

// T-G7-FIXES-F | F10 instructor agreement empty state, DemoSeeder::agreement publishing and Karim's acceptance

use App\Domain\Instructors\Models\InstructorAgreementAcceptance;
use App\Domain\Instructors\Queries\InstructorAgreements;
use App\Filament\Pages\InstructorAgreementPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('instructor');
});

afterEach(fn () => app()->setLocale('en'));

function g7fIndependent(): User
{
    return User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('instructor_independent');
}

it('F10 an independent instructor with no published agreement sees the empty state in English', function (): void {
    app()->setLocale('en');
    $this->actingAs(g7fIndependent());

    $html = Livewire::test(InstructorAgreementPage::class)->assertOk()->html();

    expect($html)->toContain('data-agreement-empty')
        ->and($html)->toContain(__('instructor_onboarding.agreement.none_published', [], 'en'))
        ->and($html)->toContain(__('instructor_onboarding.agreement.none_published_help', [], 'en'))
        ->and($html)->toContain(route('contact.show'))
        ->and($html)->toContain(__('instructor_onboarding.agreement.none_published_contact', [], 'en'))
        ->and($html)->not->toContain('data-agreement-version');
});

it('F10 the empty state explains the missing agreement and links to contact in Arabic', function (): void {
    app()->setLocale('ar');
    $this->actingAs(g7fIndependent());

    $html = Livewire::test(InstructorAgreementPage::class)->assertOk()->html();

    expect($html)->toContain('data-agreement-empty')
        ->and($html)->toContain(__('instructor_onboarding.agreement.none_published', [], 'ar'))
        ->and($html)->toContain(route('contact.show'))
        ->and($html)->not->toContain('data-agreement-version');
});

it('F10 demo:seed publishes the placeholder agreement and records Karim acceptance', function (): void {
    $this->artisan('demo:seed')->assertExitCode(0);

    $version = (string) config('instructor_onboarding.placeholder_agreement_version');
    $current = InstructorAgreements::current();

    expect($current)->not->toBeNull()
        ->and($current?->version)->toBe($version);

    $karim = User::query()->where('email', 'instructor.indie@'.Config::string('demo.email_domain'))->firstOrFail();

    expect(InstructorAgreementAcceptance::query()->where('user_id', $karim->getKey())->where('version', $version)->exists())->toBeTrue();
});

it('F10 after demo:seed Karim sees the published version and the already-accepted state', function (): void {
    $this->artisan('demo:seed')->assertExitCode(0);

    $karim = User::query()->where('email', 'instructor.indie@'.Config::string('demo.email_domain'))->firstOrFail();
    $this->actingAs($karim);

    $version = (string) config('instructor_onboarding.placeholder_agreement_version');
    $html = Livewire::test(InstructorAgreementPage::class)->assertOk()->html();

    expect($html)->not->toContain('data-agreement-empty')
        ->and($html)->toContain('data-agreement-version')
        ->and($html)->toContain($version)
        ->and($html)->toContain('data-agreement-accepted')
        ->and($html)->not->toContain('data-agreement-required');
});
