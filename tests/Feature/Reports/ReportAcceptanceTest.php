<?php

// T-P9-06 | PRD 21, Checklist 11 | A1 A2 A3

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Filament\Pages\ReportsPage;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

function tp906ReportCourse(string $slug, string $title): Course
{
    return Course::query()->create([
        'title' => ['en' => $title],
        'slug_ar' => $slug,
        'slug_en' => $slug,
        'status' => 'published',
        'access_mode' => 'free',
    ]);
}

it('reconciles finance report rows and totals per currency to paid orders and executed refunds', function (): void {
    $finance = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('finance');
    $payer = User::factory()->create();
    $input = ['from' => '2026-11-10', 'to' => '2026-11-15'];

    $orders = [];
    foreach ([
        ['EG-01', 'EG', 'EGP', 10000, 'partially_refunded', '2026-11-12 10:00:00'],
        ['EG-02', 'EG', 'EGP', 5050, 'paid', '2026-11-12 10:00:00'],
        ['SA-01', 'SA', 'SAR', 2500, 'partially_refunded', '2026-11-12 10:00:00'],
        ['PENDING', 'EG', 'EGP', 7000, 'pending', null],
        ['FAILED', 'EG', 'EGP', 6000, 'failed', null],
        ['OUTSIDE', 'EG', 'EGP', 9000, 'paid', '2026-11-09 10:00:00'],
    ] as [$reference, $market, $currency, $amount, $status, $paidAt]) {
        $orders[$reference] = DB::table('orders')->insertGetId([
            'reference' => $reference, 'payer_id' => $payer->id, 'market' => $market, 'currency' => $currency,
            'subtotal_minor' => $amount, 'total_minor' => $amount, 'status' => $status, 'paid_at' => $paidAt,
            'idempotency_key' => $reference, 'request_hash' => hash('sha256', $reference),
        ]);
    }

    foreach ([
        ['REF-01', 'EG-01', 'EGP', 1500, 'executed', '2026-11-13 10:00:00'],
        ['REF-02', 'EG-01', 'EGP', 500, 'executed', '2026-11-16 10:00:00'],
        ['REF-03', 'EG-02', 'EGP', 300, 'requested', null],
        ['REF-04', 'SA-01', 'SAR', 250, 'executed', '2026-11-13 10:00:00'],
    ] as [$reference, $order, $currency, $amount, $status, $executedAt]) {
        DB::table('refunds')->insert([
            'reference' => $reference, 'order_id' => $orders[$order], 'currency' => $currency, 'amount_minor' => $amount,
            'reason' => 'Fixture refund', 'method' => 'manual', 'status' => $status,
            'requested_by' => $finance->id, 'executed_at' => $executedAt,
        ]);
    }
    $coupon = DB::table('coupons')->insertGetId(['code' => 'REPORT25', 'name' => 'Report discount', 'discount_type' => 'percent', 'percent' => 25]);
    DB::table('coupon_redemptions')->insert([
        'coupon_id' => $coupon, 'order_id' => $orders['EG-01'], 'user_id' => $payer->id, 'discount_minor' => 3000,
        'currency' => 'EGP', 'status' => 'redeemed', 'reserved_at' => '2026-11-11', 'redeemed_at' => '2026-11-12',
    ]);
    foreach (['failed', 'succeeded'] as $sequence => $status) {
        DB::table('payment_attempts')->insert([
            'order_id' => $orders['EG-01'], 'gateway' => 'paymob', 'sequence' => $sequence + 1, 'reference' => 'REPORT-'.$status,
            'status' => $status, 'amount_minor' => 10000, 'currency' => 'EGP', 'expires_at' => '2026-11-12 12:00:00',
        ]);
    }
    DB::table('manual_payment_proofs')->insert([
        'order_id' => $orders['EG-02'], 'payer_id' => $payer->id, 'payer_name' => 'Private payer',
        'sender_account' => 'Private sender', 'transfer_reference' => 'PRIVATE-REPORT-PROOF',
        'amount_minor' => 4500, 'currency' => 'EGP', 'transferred_at' => '2026-11-11', 'file_path' => 'private-slip.pdf',
        'file_mime' => 'application/pdf', 'file_size' => 10, 'file_sha256' => hash('sha256', 'report-fixture'),
        'status' => 'approved', 'sla_due_at' => '2026-11-13', 'reviewed_at' => '2026-11-12 10:00:00',
    ]);

    $catalog = app(ReportCatalog::class);
    $filters = ReportFilters::fromInput($input);
    $sales = $catalog->definition(ReportKey::Sales);
    $refunds = $catalog->definition(ReportKey::Refunds);
    $salesFilters = $catalog->scope($finance, ReportKey::Sales, $filters);
    $refundFilters = $catalog->scope($finance, ReportKey::Refunds, $filters);
    $salesRows = iterator_to_array($sales->rows($salesFilters));
    $refundRows = iterator_to_array($refunds->rows($refundFilters));
    $salesTotals = collect($sales->totals($salesFilters));
    $bookedTotals = $salesTotals->where('key', '!=', 'recorded_collections')->values()->all();

    expect(array_column($salesRows, 0))->toBe(['EG-01', 'EG-02', 'SA-01'])
        ->and(array_column($salesRows, 7))->toBe([100.0, 50.5, 25.0])
        ->and(array_column($salesRows, 8))->toBe([20.0, 0.0, 2.5])
        ->and(array_column($salesRows, 9))->toBe([80.0, 50.5, 22.5])
        ->and(array_column($bookedTotals, 'value'))->toBe([2, 150.5, 20.0, 130.5, 1, 25.0, 2.5, 22.5])
        ->and(array_column($bookedTotals, 'currency'))->toBe(['EGP', 'EGP', 'EGP', 'EGP', 'SAR', 'SAR', 'SAR', 'SAR'])
        ->and(array_column($refundRows, 0))->toBe(['REF-01', 'REF-04'])
        ->and(array_column($refundRows, 7))->toBe([15.0, 2.5])
        ->and(array_column($refunds->totals($refundFilters), 'value'))->toBe([1, 15.0, 1, 2.5]);
    expect(array_combine(array_keys($sales->columns()), $salesRows[0]))
        ->toMatchArray(['method' => 'Paymob', 'coupon_code' => 'REPORT25', 'recorded_collections' => 100.0, 'collection_currency' => 'EGP'])
        ->and(array_combine(array_keys($sales->columns()), $salesRows[1]))
        ->toMatchArray([
            'method' => 'Instapay / manual transfer', 'manual_approved_at' => '2026-11-12 10:00:00',
            'total' => 50.5, 'recorded_collections' => 45.0, 'collection_currency' => 'EGP',
        ])
        ->and($salesTotals->where('key', 'recorded_collections')->pluck('value', 'currency')->all())->toBe(['EGP' => 145.0]);

    $csv = $this->actingAs($finance)->get(route('reports.export', ['report' => 'sales'] + $input))
        ->assertOk()->streamedContent();
    expect($csv)->toContain('REPORT25')->not->toContain('PRIVATE-REPORT-PROOF', 'Private payer', 'private-slip.pdf');
    $lines = explode("\n", trim($csv));
    $rows = array_map(static fn (string $line): array => str_getcsv($line, escape: ''), array_slice($lines, 1));

    expect(array_column($rows, 0))->toBe(['EG-01', 'EG-02', 'SA-01'])
        ->and(array_map('floatval', array_column($rows, 9)))->toBe([80.0, 50.5, 22.5]);
})->group('T-P9-06', 'A1');

it('enforces assigned course scope even with another instructor or course in report filters', function (): void {
    $instructor = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('instructor_academy');
    $other = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $assigned = tp906ReportCourse('report-assigned', 'Assigned workshop');
    $outside = tp906ReportCourse('report-outside', 'Outside workshop');
    $assigned->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => 'manage']);
    $outside->instructors()->attach($other, ['role' => 'instructor', 'scope' => 'manage']);
    app(GrantEntitlement::class)($learner, $assigned, EntitlementSource::Free);
    app(GrantEntitlement::class)($learner, $outside, EntitlementSource::Free);

    $catalog = app(ReportCatalog::class);
    $key = ReportKey::Enrollments;
    $report = $catalog->definition($key);
    $own = $catalog->scope($instructor, $key, ReportFilters::fromInput(['instructor' => $other->id]));
    $forged = $catalog->scope($instructor, $key, ReportFilters::fromInput(['course' => $outside->id]));

    expect(array_column(iterator_to_array($report->rows($own)), 0))->toBe(['Assigned workshop'])
        ->and(iterator_to_array($report->rows($forged)))->toBe([])
        ->and(array_column($report->totals($forged), 'value'))->toBe([0, 0, 0, 0])
        ->and($catalog->available($instructor))->toBe([
            ReportKey::Enrollments, ReportKey::CohortAttendance, ReportKey::AssessmentResults,
            ReportKey::Certificates, ReportKey::InstructorEarnings, ReportKey::InstructorOperations,
            ReportKey::HelpSla, ReportKey::StudentProgress, ReportKey::Content,
        ]);

    Filament::setCurrentPanel(Filament::getPanel('instructor'));
    $this->actingAs($instructor);
    Livewire::test(ReportsPage::class)
        ->set('report', $key->value)
        ->assertSee('Assigned workshop')
        ->assertDontSee('Outside workshop')
        ->set('course', (string) $outside->id)
        ->assertSeeHtml('data-report-empty');

    $csv = $this->get(route('reports.export', ['report' => $key->value, 'course' => $outside->id]))
        ->assertOk()->streamedContent();
    expect(explode("\n", trim($csv)))->toHaveCount(1);

    foreach (['sales', 'course_sales', 'refunds', 'reconciliation', 'ai_usage', 'platform_overview'] as $restricted) {
        $this->get(route('reports.export', ['report' => $restricted]))->assertForbidden();
    }
})->group('T-P9-06', 'A2');

it('keeps AI and help reports aggregate when stored conversations contain private text', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    $student = User::factory()->create(['id' => 8878128, 'name' => 'Private Student Name', 'email' => 'hidden-student@example.test']);
    $course = tp906ReportCourse('report-help', 'Help workshop');
    $lesson = Lesson::query()->create(['title' => ['en' => 'Report lesson']]);
    $hash = str_repeat('a', 64);
    $date = '2026-11-12 10:00:00';
    $input = ['from' => '2026-11-10', 'to' => '2026-11-15'];
    $conversation = AiConversation::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
    ]);
    $message = AiMessage::query()->create([
        'conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'PRIVATE_AI_MESSAGE',
    ]);
    foreach (['answered', 'provider_error'] as $result) {
        DB::table('ai_usage')->insert([
            'student_hash' => $hash, 'lesson_id' => $lesson->id, 'provider' => 'fake',
            'cost_minor' => 25, 'result' => $result, 'created_at' => $date,
        ]);
    }
    $request = DB::table('help_requests')->insertGetId([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'conversation_id' => $conversation->id, 'ai_message_id' => $message->id,
        'locale' => 'en', 'document_version' => 1, 'question' => 'PRIVATE_HELP_QUESTION',
        'ai_answer' => 'PRIVATE_HELP_ANSWER', 'created_at' => $date, 'first_replied_at' => '2026-11-12 10:10:00',
    ]);
    DB::table('help_request_messages')->insert([
        'help_request_id' => $request, 'author_id' => $student->id, 'role' => 'student', 'body' => 'PRIVATE_HELP_MESSAGE',
    ]);

    $catalog = app(ReportCatalog::class);
    $filters = ReportFilters::fromInput($input);
    $ai = $catalog->definition(ReportKey::AiUsage);
    $help = $catalog->definition(ReportKey::HelpSla);
    $aiFilters = $catalog->scope($admin, ReportKey::AiUsage, $filters);
    expect(array_keys($ai->columns()))->toBe(['day', 'requests', 'errors', 'cost'])
        ->and(iterator_to_array($ai->rows($aiFilters)))->toBe([['2026-11-12', 2, 1, 0.5]])
        ->and(array_slice(array_column($ai->totals($aiFilters), 'value', 'key'), 0, 3))
        ->toBe(['ai_requests' => 2, 'ai_errors' => 1, 'ai_cost' => 0.5])
        ->and(iterator_to_array($help->rows($catalog->scope($admin, ReportKey::HelpSla, $filters))))
        ->toBe([['Help workshop', 1, 1, 0, 0, 1, 10]]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);
    $page = Livewire::test(ReportsPage::class)
        ->set('from', $input['from'])
        ->set('to', $input['to'])
        ->set('report', 'ai_usage')
        ->assertSee('12/11/2026');
    $csv = $this->get(route('reports.export', ['report' => 'ai_usage'] + $input))->assertOk()->streamedContent();
    expect(trim($csv))->toBe("\xEF\xBB\xBFDay,Requests,Failures,Cost\n2026-11-12,2,1,0.5");

    foreach (['PRIVATE_AI_MESSAGE', 'PRIVATE_HELP_QUESTION', 'PRIVATE_HELP_ANSWER', 'PRIVATE_HELP_MESSAGE', $student->name, $student->email, (string) $student->id, $hash, 'student_hash', 'student_id'] as $private) {
        $page->assertDontSee($private);
    }
})->group('T-P9-06', 'A3');
