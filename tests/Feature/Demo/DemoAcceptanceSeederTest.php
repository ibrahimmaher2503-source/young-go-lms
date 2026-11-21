<?php

// T-P9-11 | YG-GAP-OPS-003, ED-022 | A2

declare(strict_types=1);

use App\Domain\AI\AssistantEligibility;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ValidateCoupon;
use App\Domain\Commerce\CouponQuote;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Demo\DemoAnchors;
use App\Domain\InstructorFinance\Actions\ApproveSettlement;
use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Live\JoinWindow;
use App\Domain\Settings\PlatformFlag;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\LessonCurriculumDocument;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('public');
    Storage::fake(config('media.disk'));
    app()->setLocale('en');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function tp911a2Seed(): void
{
    test()->artisan('demo:seed')->assertExitCode(0);
}

function tp911a2Demo(string $key): User
{
    return User::query()->where('email', $key.'@'.Config::string('demo.email_domain'))->sole();
}

function tp911a2Course(string $key): Course
{
    $title = DemoSeeder::blueprint()[$key][1];

    return Course::query()->whereKey(DemoAnchors::ids('courses'))->get()
        ->first(static fn (Course $course): bool => $course->getTranslation('title', 'en') === $title)
        ?? throw new RuntimeException('The demo course '.$key.' is missing.');
}

/** @return array<string, int> */
function tp911a2Counts(): array
{
    $tables = ['users', 'courses', 'orders', 'live_sessions', 'course_revisions', 'instructor_ledger_entries', 'instructor_statements', 'demo_anchors'];
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    return $out;
}

it('turns the lesson assistant on with the fake provider, keeps ai.minors off and admits only the adult learner', function (): void {
    Feature::for(null)->activate(PlatformFlag::AiMinors->value);

    tp911a2Seed();

    expect(Feature::for(null)->active(PlatformFlag::AiAssistant->value))->toBeTrue()
        ->and(Feature::for(null)->active(PlatformFlag::AiMinors->value))->toBeFalse()
        ->and(ProviderRegistry::currentKey())->toBe(ProviderRegistry::FAKE)
        ->and(DB::table('flag_approvals')->where('flag', PlatformFlag::AiAssistant->value)->exists())->toBeTrue();

    $lesson = LessonCurriculumDocument::query()->where('active', true)->sole()->lesson;
    $eligibility = app(AssistantEligibility::class);

    expect($eligibility->check(tp911a2Demo('learner.omar'), $lesson)->isAvailable())->toBeTrue()
        ->and($eligibility->check(User::query()->where('username', 'demo.adam')->sole(), $lesson)->isAvailable())->toBeFalse();
})->group('T-P9-11', 'A2');

it('leaves the assistant flag off and never uses a real provider when the fake provider is not active', function (): void {
    // Outside local/testing the registry offers only the real providers, so the seeder cannot pick the fake one.
    $this->app['env'] = 'staging';
    Config::set('ai.provider', ProviderRegistry::OPENAI);
    Config::set('ai.openai.api_key', null);

    tp911a2Seed();

    expect(Feature::for(null)->active(PlatformFlag::AiAssistant->value))->toBeFalse()
        ->and(Feature::for(null)->active(PlatformFlag::AiMinors->value))->toBeFalse()
        ->and(ProviderRegistry::currentKey())->not->toBe(ProviderRegistry::FAKE);
})->group('T-P9-11', 'A2');

it('opens the Open studio join window 5 minutes after the reset and ends the session 8 hours later', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'UTC'));
    tp911a2Seed();

    test()->artisan('demo:reset')->assertExitCode(0);

    $session = LiveSession::query()->where('title->en', 'Open studio: full-day rehearsal')->sole();
    expect(JoinWindow::opensAt($session)->equalTo(now()->addMinutes(5)))->toBeTrue()
        ->and(JoinWindow::state($session, now()->addMinutes(4)))->toBe(JoinWindow::UPCOMING)
        ->and(JoinWindow::state($session, now()->addMinutes(6)))->toBe(JoinWindow::JOINABLE)
        ->and($session->ends_at->equalTo(now()->addHours(8)))->toBeTrue();
})->group('T-P9-11', 'A2');

it('gives Karim a payout profile, a last-month statement and 98000 accrued, and a settlement batch can be created and approved', function (): void {
    tp911a2Seed();
    $karim = tp911a2Demo('instructor.indie');

    expect(PayoutProfile::query()->where('user_id', $karim->id)->exists())->toBeTrue()
        ->and(InstructorStatement::query()->where('instructor_id', $karim->id)
            ->where('period', now()->subMonthNoOverflow()->format('Y-m'))->exists())->toBeTrue();

    $entries = LedgerEntry::query()->where('instructor_id', $karim->id)->where('status', LedgerStatus::Accrued->value)->get();
    expect((int) $entries->sum('amount_minor'))->toBe(98000);

    $batch = app(CreateSettlementBatch::class)(tp911a2Demo('admin'), 'EGP', $entries->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(), 'T-P9-11 demo batch', 'tp911-demo-batch');
    expect($batch->status)->toBe(SettlementStatus::Draft);

    // Four-eyes: the creator cannot approve, so a second finance user with settlement.manage approves it.
    $approver = User::factory()->create(['status' => 'active']);
    $approver->givePermissionTo('settlement.manage');

    expect(app(ApproveSettlement::class)($approver, $batch)->status)->toBe(SettlementStatus::Approved);
})->group('T-P9-11', 'A2');

it('leaves one pending revision of the independent course indie-comics', function (): void {
    tp911a2Seed();

    expect(CourseRevision::query()->where('course_id', tp911a2Course('indie-comics')->getKey())
        ->where('status', CourseRevisionStatus::Pending->value)->count())->toBe(1);
})->group('T-P9-11', 'A2');

it('validates the coupon DEMO-WELCOME10 for Omar on acting-101', function (): void {
    tp911a2Seed();
    $omar = tp911a2Demo('learner.omar');
    $order = app(CreateOrder::class)($omar, Market::Egypt, [new OrderLine(ProductType::Course, (int) tp911a2Course('acting-101')->getKey(), $omar)], 'tp911-coupon-omar');

    $quote = app(ValidateCoupon::class)('DEMO-WELCOME10', $omar, $order);

    expect($quote)->toBeInstanceOf(CouponQuote::class)
        ->and($quote->discountMinor)->toBeGreaterThan(0);
})->group('T-P9-11', 'A2');

it('gives the same counts after the demo reset runs twice', function (): void {
    tp911a2Seed();

    test()->artisan('demo:reset')->assertExitCode(0);
    $first = tp911a2Counts();
    test()->artisan('demo:reset')->assertExitCode(0);

    expect(tp911a2Counts())->toBe($first);
})->group('T-P9-11', 'A2');
