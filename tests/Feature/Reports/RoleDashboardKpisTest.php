<?php

// T-P9-07 | PRD 2.3, Checklist 11 | A1 A2

declare(strict_types=1);

use App\Domain\Commerce\Queries\ManualPaymentQueue;
use App\Domain\Commerce\Queries\RefundQueue;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Reports\Dashboards\DashboardData;
use App\Domain\Reports\Dashboards\DashboardKpi;
use App\Domain\Reports\Dashboards\DashboardMoney;
use App\Domain\Reports\Dashboards\RoleDashboard;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Shared\Markets\Market;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
    Config::set('reports.dashboard_cache_seconds', 0);
    Config::set('reports.dashboard_days', 30);
    Cache::flush();
});

/** Course with a title and a published, free status (same shape the catalog tests use). */
function tp907Course(string $slug, string $title): Course
{
    return Course::query()->create([
        'title' => ['en' => $title],
        'slug_ar' => $slug,
        'slug_en' => $slug,
        'status' => 'published',
        'access_mode' => 'free',
    ]);
}

function tp907Order(User $payer, string $reference, string $market, string $currency, int $amount, string $status, ?string $paidAt): int
{
    return DB::table('orders')->insertGetId([
        'reference' => $reference, 'payer_id' => $payer->id, 'market' => $market, 'currency' => $currency,
        'subtotal_minor' => $amount, 'total_minor' => $amount, 'status' => $status, 'paid_at' => $paidAt,
        'idempotency_key' => $reference, 'request_hash' => hash('sha256', $reference),
    ]);
}

function tp907Refund(int $orderId, User $by, string $reference, string $currency, int $amount, string $status, ?string $executedAt): void
{
    DB::table('refunds')->insert([
        'reference' => $reference, 'order_id' => $orderId, 'currency' => $currency, 'amount_minor' => $amount,
        'reason' => 'Fixture refund', 'method' => 'manual', 'status' => $status,
        'requested_by' => $by->id, 'executed_at' => $executedAt,
    ]);
}

function tp907Completion(Course $course, User $learner, string $at): void
{
    DB::table('course_completions')->insert([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => $at, 'source' => 'auto',
    ]);
}

function tp907Ledger(User $instructor, ?Course $course, string $status, int $amount, string $currency, string $key): void
{
    DB::table('instructor_ledger_entries')->insert([
        'instructor_id' => $instructor->id, 'course_id' => $course?->id, 'type' => 'earning', 'sale_type' => 'individual',
        'status' => $status, 'source_key' => $key, 'currency' => $currency, 'amount_minor' => $amount,
        'earned_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Seeds paid orders in EGP and SAR, executed and requested refunds, enrollments, completions and AI usage rows inside the 30-day window. */
function tp907SeedAdminData(): array
{
    $payer = User::factory()->create(['status' => 'active']);
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $paidAt = now()->subDays(3)->toDateTimeString();

    $eg1 = tp907Order($payer, 'T907-EG-01', 'EG', 'EGP', 10000, 'partially_refunded', $paidAt);
    $eg2 = tp907Order($payer, 'T907-EG-02', 'EG', 'EGP', 5050, 'paid', $paidAt);
    $sa1 = tp907Order($payer, 'T907-SA-01', 'SA', 'SAR', 2500, 'partially_refunded', $paidAt);
    tp907Order($payer, 'T907-PENDING', 'EG', 'EGP', 7000, 'pending', null);
    tp907Order($payer, 'T907-FAILED', 'EG', 'EGP', 6000, 'failed', null);
    tp907Order($payer, 'T907-OLD', 'EG', 'EGP', 9000, 'paid', now()->subDays(40)->toDateTimeString());

    tp907Refund($eg1, $admin, 'T907-REF-01', 'EGP', 1500, 'executed', now()->subDays(2)->toDateTimeString());
    tp907Refund($eg1, $admin, 'T907-REF-02', 'EGP', 500, 'executed', now()->subDays(1)->toDateTimeString());
    tp907Refund($eg2, $admin, 'T907-REF-03', 'EGP', 300, 'requested', null);
    tp907Refund($sa1, $admin, 'T907-REF-04', 'SAR', 250, 'executed', now()->subDays(2)->toDateTimeString());

    $course = tp907Course('t907-admin', 'Admin dashboard course');
    $learners = [User::factory()->create(['status' => 'active']), User::factory()->create(['status' => 'active'])];
    foreach ($learners as $learner) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }
    tp907Completion($course, $learners[0], now()->subDays(2)->toDateTimeString());
    tp907Completion($course, $learners[1], now()->subDays(60)->toDateTimeString());

    $lesson = Lesson::query()->create(['title' => ['en' => 'T907 lesson']]);
    foreach (['answered', 'provider_error'] as $result) {
        DB::table('ai_usage')->insert([
            'student_hash' => str_repeat('b', 64), 'lesson_id' => $lesson->id, 'provider' => 'fake',
            'cost_minor' => 25, 'result' => $result, 'created_at' => now()->subDays(2)->toDateTimeString(),
        ]);
    }

    return [$admin, $course];
}

/** Money per currency from report totals (major units to minor units), the way the dashboard maps them. */
function tp907MinorFromTotals(array $totals, string $key): array
{
    $out = [];
    foreach ($totals as $total) {
        if ($total['key'] === $key && $total['currency'] !== null) {
            $out[$total['currency']] = ($out[$total['currency']] ?? 0) + (int) round($total['value'] * 100);
        }
    }
    ksort($out);

    return $out;
}

function tp907Kpi(DashboardData $data, string $key): ?DashboardKpi
{
    foreach ([...$data->kpis, ...$data->queues] as $kpi) {
        if ($kpi->key === $key) {
            return $kpi;
        }
    }

    return null;
}

it('equals the admin KPI tiles to the sales, refunds, enrollments, completions and AI report totals of the same window', function (): void {
    [$admin] = tp907SeedAdminData();
    $catalog = app(ReportCatalog::class);
    $to = now(Market::Egypt->timezone());
    $window = ReportFilters::fromInput(['from' => $to->copy()->subDays(29)->toDateString(), 'to' => $to->toDateString()]);

    $sales = $catalog->definition(ReportKey::Sales)->totals($catalog->scope($admin, ReportKey::Sales, $window));
    $refunds = $catalog->definition(ReportKey::Refunds)->totals($catalog->scope($admin, ReportKey::Refunds, $window));
    $enrollments = $catalog->definition(ReportKey::Enrollments)->totals($catalog->scope($admin, ReportKey::Enrollments, $window));
    $ai = $catalog->definition(ReportKey::AiUsage)->totals($catalog->scope($admin, ReportKey::AiUsage, $window));

    $data = app(RoleDashboard::class)->for($admin, 'admin');

    expect(tp907Kpi($data, 'revenue')->money)->toBe(tp907MinorFromTotals($sales, 'net'))
        ->and(tp907Kpi($data, 'revenue')->money)->toBe(['EGP' => 13050, 'SAR' => 2250])
        ->and(tp907Kpi($data, 'orders')->value)->toBe(3)
        ->and(tp907Kpi($data, 'refunds')->money)->toBe(tp907MinorFromTotals($refunds, 'refund_amount'))
        ->and(tp907Kpi($data, 'refunds')->money)->toBe(['EGP' => 2000, 'SAR' => 250])
        ->and(tp907Kpi($data, 'refunds')->secondary)->toBe(3)
        ->and(tp907Kpi($data, 'active_learners')->value)->toBe(EnrollmentReport::activeLearners(null))
        ->and(tp907Kpi($data, 'active_learners')->value)->toBe(2)
        ->and(tp907Kpi($data, 'completions')->value)->toBe(1)
        ->and(tp907Kpi($data, 'ai_requests')->value)->toBe(2)
        ->and(tp907Kpi($data, 'ai_requests')->secondary)->toBe(1)
        ->and(tp907Kpi($data, 'ai_cost')->money)->toBe(tp907MinorFromTotals($ai, 'ai_cost'))
        ->and($enrollments)->not->toBeEmpty();
})->group('T-P9-07', 'A1');

it('keeps finance KPIs equal to sales net and the ledger balances, with held money separate', function (): void {
    tp907SeedAdminData();
    $finance = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('finance');
    $instructor = User::factory()->create(['status' => 'active']);
    tp907Ledger($instructor, null, 'accrued', 1000, 'EGP', 'T907-L1');
    tp907Ledger($instructor, null, 'pending', 500, 'SAR', 'T907-L2');
    tp907Ledger($instructor, null, 'approved', 200, 'EGP', 'T907-L3');
    tp907Ledger($instructor, null, 'held', 300, 'EGP', 'T907-L4');
    tp907Ledger($instructor, null, 'paid', 700, 'EGP', 'T907-L5');
    tp907Ledger($instructor, null, 'reversed', 800, 'EGP', 'T907-L6');

    $catalog = app(ReportCatalog::class);
    $to = now(Market::Egypt->timezone());
    $window = ReportFilters::fromInput(['from' => $to->copy()->subDays(29)->toDateString(), 'to' => $to->toDateString()]);
    $sales = $catalog->definition(ReportKey::Sales)->totals($catalog->scope($finance, ReportKey::Sales, $window));

    $data = app(RoleDashboard::class)->for($finance, 'finance');

    expect(tp907Kpi($data, 'revenue')->money)->toBe(tp907MinorFromTotals($sales, 'net'))
        ->and(tp907Kpi($data, 'instructor_balances')->money)->toBe(['EGP' => 1200, 'SAR' => 500])
        ->and(tp907Kpi($data, 'instructor_held')->money)->toBe(['EGP' => 300])
        ->and(tp907Kpi($data, 'active_learners'))->toBeNull()
        ->and(tp907Kpi($data, 'ai_requests'))->toBeNull()
        ->and(tp907Kpi($data, 'completions'))->toBeNull();
})->group('T-P9-07', 'A1');

it('reads each queue live and equal to its queue query', function (): void {
    [$admin] = tp907SeedAdminData();
    $queues = app(RoleDashboard::class)->for($admin, 'admin')->queues;
    $byKey = [];
    foreach ($queues as $queue) {
        $byKey[$queue->key] = $queue;
    }

    expect(ManualPaymentQueue::summary()['pending'])->toBe($byKey['manual_payments']->value)
        ->and(RefundQueue::summary()['requested'])->toBe($byKey['refunds_requested']->value)
        ->and($byKey['refunds_requested']->value)->toBe(1)
        ->and(array_key_exists('reviews_pending', $byKey))->toBeTrue()
        ->and($byKey['instructor_applications']->value)->toBe(0);
})->group('T-P9-07', 'A1');

it('isolates instructor A to course A learners, completions, ledger and assigned help requests', function (): void {
    $payer = User::factory()->create(['status' => 'active']);
    $instructorA = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('instructor_academy');
    $instructorB = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('instructor_academy');
    $courseA = tp907Course('t907-a', 'Course A');
    $courseB = tp907Course('t907-b', 'Course B');
    $courseA->instructors()->attach($instructorA, ['role' => 'instructor', 'scope' => 'manage']);
    $courseB->instructors()->attach($instructorB, ['role' => 'instructor', 'scope' => 'manage']);

    $a1 = User::factory()->create(['status' => 'active']);
    $a2 = User::factory()->create(['status' => 'active']);
    $b1 = User::factory()->create(['status' => 'active']);
    app(GrantEntitlement::class)($a1, $courseA, EntitlementSource::Free);
    app(GrantEntitlement::class)($a2, $courseA, EntitlementSource::Free);
    app(GrantEntitlement::class)($b1, $courseB, EntitlementSource::Free);
    tp907Completion($courseA, $a1, now()->subDays(2)->toDateTimeString());
    tp907Completion($courseB, $b1, now()->subDays(2)->toDateTimeString());

    tp907Ledger($instructorA, $courseA, 'accrued', 1000, 'EGP', 'T907-A1');
    tp907Ledger($instructorB, $courseB, 'accrued', 4000, 'EGP', 'T907-B1');

    $lesson = Lesson::query()->create(['title' => ['en' => 'T907 help lesson']]);
    foreach ([[$instructorA, $courseA], [$instructorB, $courseB]] as [$who, $course]) {
        $conversation = AiConversation::query()->create([
            'student_id' => $payer->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'T907 answer',
        ]);
        DB::table('help_requests')->insert([
            'student_id' => $payer->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'locale' => 'en',
            'conversation_id' => $conversation->id, 'ai_message_id' => $message->id, 'document_version' => 1,
            'question' => 'T907 question', 'ai_answer' => 'T907 answer', 'status' => 'new',
            'assigned_instructor_id' => $who->id, 'due_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $data = app(RoleDashboard::class)->for($instructorA, 'instructor_academy');

    expect(tp907Kpi($data, 'my_learners')->value)->toBe(2)
        ->and(tp907Kpi($data, 'completions')->value)->toBe(1)
        ->and(tp907Kpi($data, 'completion_rate')->value)->toBe(50.0)
        ->and(tp907Kpi($data, 'earnings')->money)->toBe(['EGP' => 1000])
        ->and(tp907Kpi($data, 'help_awaiting')->value)->toBe(1)
        ->and(tp907Kpi($data, 'help_awaiting')->secondary)->toBe(1)
        ->and(tp907Kpi($data, 'revenue'))->toBeNull()
        ->and(tp907Kpi($data, 'refunds'))->toBeNull()
        ->and(tp907Kpi($data, 'orders'))->toBeNull()
        ->and(tp907Kpi($data, 'ai_requests'))->toBeNull()
        ->and(tp907Kpi($data, 'manual_payments'))->toBeNull();

    $dataB = app(RoleDashboard::class)->for($instructorB, 'instructor_academy');
    expect(tp907Kpi($dataB, 'my_learners')->value)->toBe(1)
        ->and(tp907Kpi($dataB, 'earnings')->money)->toBe(['EGP' => 4000])
        ->and(tp907Kpi($dataB, 'help_awaiting')->value)->toBe(1);

    $adminData = app(RoleDashboard::class)->for(User::factory()->create(['status' => 'active'])->assignRole('admin'), 'admin');
    expect(tp907Kpi($adminData, 'active_learners')->value)->toBe(3);
})->group('T-P9-07', 'A2');

it('formats money per currency in the locale and falls back for an unknown currency', function (): void {
    app()->setLocale('en');
    expect(DashboardMoney::format(123450, 'EGP'))->toContain('EGP')->toContain('1,234.50')
        ->and(DashboardMoney::format(123450, 'XYZ'))->toBe('1,234.50 XYZ');

    app()->setLocale('ar');
    $arabic = DashboardMoney::format(123450, 'SAR');
    expect($arabic)->toContain('1,234.50')
        ->and($arabic)->not->toContain('SAR');

    app()->setLocale('en');
})->group('T-P9-07', 'A1');

it('renders empty states on a new database and gives no data to students or suspended staff', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $empty = app(RoleDashboard::class)->for($admin, 'admin');
    expect(tp907Kpi($empty, 'revenue')->isEmpty())->toBeTrue()
        ->and(tp907Kpi($empty, 'orders')->isEmpty())->toBeTrue()
        ->and(tp907Kpi($empty, 'manual_payments')->isEmpty())->toBeTrue();

    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    expect(app(RoleDashboard::class)->for($student, 'instructor_academy')->isEmpty())->toBeTrue();

    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    tp907SeedAdminData();
    expect(app(RoleDashboard::class)->for($suspended, 'admin')->isEmpty())->toBeTrue();

    $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSee('data-role-dashboard', false)
        ->assertDontSee('data-kpi="ai_cost"', false) // N3 (G7 review 4): an AI tile with no data is hidden, not shown as a dash (was: assertSee data-kpi-empty)
        ->assertSee('data-queue-empty', false);
})->group('T-P9-07', 'A1');

it('keeps the 30 day KPI values cached per user and panel, while queues stay live', function (): void {
    Config::set('reports.dashboard_cache_seconds', 60);
    [$admin] = tp907SeedAdminData();

    $first = tp907Kpi(app(RoleDashboard::class)->for($admin, 'admin'), 'orders')->value;
    tp907Order(User::factory()->create(['status' => 'active']), 'T907-LATE', 'EG', 'EGP', 1000, 'paid', now()->subDay()->toDateTimeString());
    $cached = tp907Kpi(app(RoleDashboard::class)->for($admin, 'admin'), 'orders')->value;

    expect($cached)->toBe($first);

    $requestedBefore = app(RoleDashboard::class)->for($admin, 'admin')->queues;
    $orderId = DB::table('orders')->where('reference', 'T907-EG-02')->value('id');
    tp907Refund((int) $orderId, $admin, 'T907-REF-LIVE', 'EGP', 100, 'requested', null);
    $queueAfter = tp907Kpi(app(RoleDashboard::class)->for($admin, 'admin'), 'refunds_requested')->value;
    $queueBefore = 0;
    foreach ($requestedBefore as $queue) {
        if ($queue->key === 'refunds_requested') {
            $queueBefore = $queue->value;
        }
    }
    expect($queueAfter)->toBe($queueBefore + 1);

    $financeFirst = app(RoleDashboard::class)->for(User::factory()->create(['status' => 'active'])->assignRole('finance'), 'finance');
    $adminAgain = app(RoleDashboard::class)->for($admin, 'admin');
    expect(tp907Kpi($adminAgain, 'ai_requests'))->not->toBeNull()
        ->and(tp907Kpi($financeFirst, 'ai_requests'))->toBeNull();

    Config::set('reports.dashboard_cache_seconds', 0);
    $fresh = tp907Kpi(app(RoleDashboard::class)->for($admin, 'admin'), 'orders')->value;
    expect($fresh)->toBe($first + 1);
})->group('T-P9-07', 'A1');

it('renders the admin dashboard KPI strip and queue rows in the page', function (): void {
    [$admin] = tp907SeedAdminData();

    $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSee('data-role-dashboard', false)
        ->assertSee('data-kpi="revenue"', false)
        ->assertSee('data-kpi="completions"', false)
        ->assertSee('data-queue="refunds_requested"', false)
        ->assertSee('data-queue-count="1"', false);
})->group('T-P9-07', 'A1');
