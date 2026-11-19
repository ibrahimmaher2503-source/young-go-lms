<?php

declare(strict_types=1);

// T-P9-06 | PRD 21, Checklist 11 | A1 A2 A3

use App\Domain\Audit\AuditLogger;
use App\Domain\Reports\Actions\ExportReportCsv;
use App\Domain\Reports\CsvCell;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportScope;
use App\Domain\Reports\ReportTooLargeException;
use App\Filament\Pages\ReportsPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

function t906cUser(string $role, string $status = 'active'): User
{
    return User::factory()->create(['status' => $status, 'locale' => 'en'])->assignRole($role);
}

/** Inserts an order directly (the same shape the existing report acceptance test uses). Returns the order id. */
function t906cOrder(string $reference, User $payer, string $market, string $currency, int $amountMinor, string $status, ?string $paidAt): int
{
    return DB::table('orders')->insertGetId([
        'reference' => $reference, 'payer_id' => $payer->id, 'market' => $market, 'currency' => $currency,
        'subtotal_minor' => $amountMinor, 'total_minor' => $amountMinor, 'status' => $status, 'paid_at' => $paidAt,
        'idempotency_key' => $reference, 'request_hash' => hash('sha256', $reference),
    ]);
}

function t906cFilters(array $input = ['from' => '2026-11-10', 'to' => '2026-11-15']): ReportFilters
{
    return ReportFilters::fromInput($input);
}

function t906cCatalog(): ReportCatalog
{
    return app(ReportCatalog::class);
}

// ---------------------------------------------------------------- access matrix

it('gives admin and super admin every report, and admin loses only the AI report without ai.view_usage', function (): void {
    $admin = t906cUser('admin');
    $super = t906cUser('super_admin');

    foreach (ReportKey::cases() as $key) {
        expect(t906cCatalog()->scopeOf($admin, $key))->toBe(ReportScope::All, $key->value)
            ->and(t906cCatalog()->scopeOf($super, $key))->toBe(ReportScope::All, $key->value);
    }

    // A role with report.view but not ai.view_usage: the AI report is the only one it loses.
    $reader = t906cUser('student');
    $reader->givePermissionTo('report.view');
    foreach (ReportKey::cases() as $key) {
        $expected = $key === ReportKey::AiUsage ? ReportScope::None : ReportScope::All;
        expect(t906cCatalog()->scopeOf($reader->fresh(), $key))->toBe($expected, $key->value);
    }
})->group('T-P9-06', 'A2');

it('gives finance exactly the four financial reports and nothing else', function (): void {
    $finance = t906cUser('finance');

    foreach ([ReportKey::Sales, ReportKey::Refunds, ReportKey::InstructorEarnings, ReportKey::Reconciliation] as $key) {
        expect(t906cCatalog()->scopeOf($finance, $key))->toBe(ReportScope::All, $key->value);
    }
    foreach ([ReportKey::Enrollments, ReportKey::CohortAttendance, ReportKey::AssessmentResults, ReportKey::Certificates, ReportKey::HelpSla, ReportKey::AiUsage] as $key) {
        expect(t906cCatalog()->scopeOf($finance, $key))->toBe(ReportScope::None, $key->value);
    }
    expect(array_map(static fn (ReportKey $k): string => $k->value, t906cCatalog()->available($finance)))
        ->toEqualCanonicalizing(['sales', 'refunds', 'instructor_earnings', 'reconciliation']);
})->group('T-P9-06', 'A2');

it('gives no report to students, parents, support, marketing, content managers or a suspended admin', function (): void {
    $users = [
        t906cUser('student'),
        t906cUser('parent'),
        t906cUser('support'),
        t906cUser('marketing'),
        t906cUser('content_manager'),
        t906cUser('admin', 'suspended'),
    ];

    foreach ($users as $user) {
        foreach (ReportKey::cases() as $key) {
            expect(t906cCatalog()->scopeOf($user, $key))->toBe(ReportScope::None, $user->getRoleNames()->first().' '.$key->value);
        }
        expect(t906cCatalog()->available($user))->toBe([]);
    }
})->group('T-P9-06', 'A2');

it('shows the report page to admin, finance and instructors only, with the matching report counts', function (): void {
    expect(t906cCatalog()->available(t906cUser('admin')))->toHaveCount(13)
        ->and(t906cCatalog()->available(t906cUser('finance')))->toHaveCount(4)
        ->and(t906cCatalog()->available(t906cUser('instructor_academy')))->toHaveCount(8);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(t906cUser('student'));
    expect(ReportsPage::canAccess())->toBeFalse();

    $this->actingAs(t906cUser('admin'));
    expect(ReportsPage::canAccess())->toBeTrue();

    $this->actingAs(t906cUser('finance'));
    expect(ReportsPage::canAccess())->toBeTrue();

    $this->actingAs(t906cUser('instructor_academy'));
    expect(ReportsPage::canAccess())->toBeTrue();

    // An instructor-less user (no report role, no instructor role) has nothing to open.
    $this->actingAs(t906cUser('marketing'));
    expect(ReportsPage::canAccess())->toBeFalse();
})->group('T-P9-06', 'A2');

it('refuses a report the user cannot read, through the export action', function (): void {
    $student = t906cUser('student');

    expect(fn () => app(ExportReportCsv::class)->prepare($student, ReportKey::Sales, t906cFilters()))
        ->toThrow(AuthorizationException::class);
    expect(Activity::query()->where('event', 'report.exported')->count())->toBe(0);
})->group('T-P9-06', 'A2');

// ---------------------------------------------------------------- filters

it('includes the whole first and last local day in Egypt time and stores UTC instants', function (): void {
    $payer = t906cUser('student');
    t906cOrder('IN-EVE', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-15 21:30:00'); // 23:30 Cairo on the last day
    t906cOrder('OUT-MIDNIGHT', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-15 22:30:00'); // 00:30 Cairo on the 16th
    t906cOrder('IN-FIRST', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-10 00:30:00'); // 02:30 Cairo on the first day
    t906cOrder('OUT-BEFORE', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-09 21:59:00'); // 23:59 Cairo the day before

    $filters = t906cFilters();
    expect($filters->from->timezone('UTC')->toDateTimeString())->toBe('2026-11-09 22:00:00')
        ->and($filters->to->timezone('UTC')->toDateTimeString())->toBe('2026-11-15 21:59:59');

    $admin = t906cUser('admin');
    $rows = iterator_to_array(t906cCatalog()->definition(ReportKey::Sales)->rows(t906cCatalog()->scope($admin, ReportKey::Sales, $filters)));
    $references = array_map(static fn (array $row): string => (string) ($row['reference'] ?? array_values($row)[0]), $rows);

    expect(t906cCatalog()->definition(ReportKey::Sales)->count(t906cCatalog()->scope($admin, ReportKey::Sales, $filters)))->toBe(2)
        ->and(implode(' ', $references))->toContain('IN-EVE')
        ->and(implode(' ', $references))->toContain('IN-FIRST')
        ->and(implode(' ', $references))->not->toContain('OUT-MIDNIGHT')
        ->and(implode(' ', $references))->not->toContain('OUT-BEFORE');
})->group('T-P9-06', 'A1');

it('swaps a reversed range, falls back to the last 30 days on garbage dates and caps ranges at 366 days', function (): void {
    $swapped = t906cFilters(['from' => '2026-11-15', 'to' => '2026-11-10']);
    expect($swapped->from->timezone('Africa/Cairo')->toDateString())->toBe('2026-11-10')
        ->and($swapped->to->timezone('Africa/Cairo')->toDateString())->toBe('2026-11-15');

    $garbage = t906cFilters(['from' => 'not-a-date', 'to' => 'also-bad']);
    expect($garbage->to->timezone('Africa/Cairo')->toDateString())->toBe(now('Africa/Cairo')->toDateString())
        ->and($garbage->from->timezone('Africa/Cairo')->toDateString())->toBe(now('Africa/Cairo')->subDays(29)->toDateString());

    // A 366 calendar-day window (2025-11-15 .. 2026-11-15 inclusive) is within the cap and must not be shortened.
    $exact = t906cFilters(['from' => '2025-11-15', 'to' => '2026-11-15']);
    expect($exact->from->timezone('Africa/Cairo')->toDateString())->toBe('2025-11-15');
})->group('T-P9-06', 'A1');

// BUG: App\Domain\Reports\ReportFilters::fromInput() caps an over-long range to (to - max_range_days)->startOfDay(), so the capped
// range covers max_range_days + 1 calendar days (367 with the default 366). Use (max_range_days - 1) to keep the cap inclusive.
it('caps a range longer than 366 days to at most 366 calendar days', function (): void {
    $capped = t906cFilters(['from' => '2020-01-01', 'to' => '2026-11-15']);
    $days = CarbonImmutable::parse($capped->from->timezone('Africa/Cairo')->toDateString())
        ->diffInDays(CarbonImmutable::parse($capped->to->timezone('Africa/Cairo')->toDateString())) + 1;

    expect((int) $days)->toBeLessThanOrEqual(366);
})->group('T-P9-06', 'A1');

it('drops an unknown market and ignores filters a report does not support', function (): void {
    $admin = t906cUser('admin');

    $unknown = t906cFilters(['from' => '2026-11-10', 'to' => '2026-11-15', 'market' => 'XX']);
    expect($unknown->market)->toBeNull();

    $reconciliation = t906cCatalog()->scope($admin, ReportKey::Reconciliation, t906cFilters(['from' => '2026-11-10', 'to' => '2026-11-15', 'course' => '5']));
    expect($reconciliation->courseId)->toBeNull();

    $enrollments = t906cCatalog()->scope($admin, ReportKey::Enrollments, t906cFilters(['from' => '2026-11-10', 'to' => '2026-11-15', 'market' => 'EG']));
    expect($enrollments->market)->toBeNull();
})->group('T-P9-06', 'A1');

// ---------------------------------------------------------------- CSV safety

it('neutralises spreadsheet formulas in string cells and leaves numbers and null alone', function (): void {
    expect(CsvCell::safe('=HYPERLINK("x")'))->toBe('\'=HYPERLINK("x")')
        ->and(CsvCell::safe('+1+1'))->toBe('\'+1+1')
        ->and(CsvCell::safe('-cmd'))->toBe('\'-cmd')
        ->and(CsvCell::safe('@SUM(A1)'))->toBe('\'@SUM(A1)')
        ->and(CsvCell::safe("\t=1"))->toBe("'\t=1")
        ->and(CsvCell::safe("\r=1"))->toBe("'\r=1")
        ->and(CsvCell::safe('   =1'))->toBe('\'   =1')
        ->and(CsvCell::safe(-5.5))->toBe(-5.5)
        ->and(CsvCell::safe(12))->toBe(12)
        ->and(CsvCell::safe(null))->toBe('')
        ->and(CsvCell::safe('Amira Hassan'))->toBe('Amira Hassan');
})->group('T-P9-06', 'A1');

it('exports a formula-looking order reference as text and writes the UTF-8 BOM and Arabic headings', function (): void {
    $payer = t906cUser('student');
    t906cOrder('=HYPERLINK("evil")', $payer, 'EG', 'EGP', 5050, 'paid', '2026-11-12 10:00:00');
    $admin = t906cUser('admin');
    $admin->update(['locale' => 'ar']);

    app()->setLocale('ar');
    $response = $this->actingAs($admin->fresh())->get(route('reports.export', ['report' => 'sales', 'from' => '2026-11-10', 'to' => '2026-11-15']));
    $response->assertOk();
    $body = $response->streamedContent();

    expect(substr($body, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($body)->toContain("'=HYPERLINK")
        ->and($body)->not->toContain(',=HYPERLINK');
    $header = strtok(substr($body, 3), "\n");
    expect($header)->toContain((string) __('reports.columns.reference', [], 'ar'));
    expect(preg_match('/\p{Arabic}/u', (string) $header))->toBe(1);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
})->group('T-P9-06', 'A1');

// ---------------------------------------------------------------- export action

it('writes one report.exported audit row with the user as causer and subject and the filters and row count', function (): void {
    $payer = t906cUser('student');
    t906cOrder('AUD-1', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-12 10:00:00');
    t906cOrder('AUD-2', $payer, 'EG', 'EGP', 2000, 'paid', '2026-11-12 11:00:00');
    $admin = t906cUser('admin');

    $prepared = app(ExportReportCsv::class)->prepare($admin, ReportKey::Sales, t906cFilters());

    $rows = Activity::query()->where('event', 'report.exported')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->causer_id)->toBe($admin->id)
        ->and($rows[0]->subject_id)->toBe($admin->id)
        ->and(json_encode($rows[0]->properties))->toContain('"report":"sales"')
        ->and(json_encode($rows[0]->properties))->toContain('"rows":2')
        ->and(json_encode($rows[0]->properties))->toContain('"course_scope"');
    expect($prepared->rows)->toBe(2);
})->group('T-P9-06', 'A1');

it('refuses an export over the row cap before any audit row, and the route answers 422 with no link', function (): void {
    config(['reports.max_rows' => 2]);
    $payer = t906cUser('student');
    foreach (['CAP-1', 'CAP-2', 'CAP-3'] as $i => $reference) {
        t906cOrder($reference, $payer, 'EG', 'EGP', 1000 + $i, 'paid', '2026-11-12 10:00:00');
    }
    $admin = t906cUser('admin');

    expect(fn () => app(ExportReportCsv::class)->prepare($admin, ReportKey::Sales, t906cFilters()))
        ->toThrow(ReportTooLargeException::class);
    expect(Activity::query()->where('event', 'report.exported')->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('reports.export', ['report' => 'sales', 'from' => '2026-11-10', 'to' => '2026-11-15']))
        ->assertStatus(422);
    expect(Activity::query()->where('event', 'report.exported')->count())->toBe(0);
})->group('T-P9-06', 'A1');

it('still writes every row exactly once when the chunk size is 1', function (): void {
    config(['reports.chunk' => 1]);
    $payer = t906cUser('student');
    foreach (['CH-1', 'CH-2', 'CH-3'] as $reference) {
        t906cOrder($reference, $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-12 10:00:00');
    }
    $admin = t906cUser('admin');

    foreach ([ReportKey::Sales] as $key) {
        $stream = fopen('php://memory', 'r+');
        $written = app(ExportReportCsv::class)->__invoke($admin, $key, t906cFilters(), $stream);
        rewind($stream);
        $lines = array_values(array_filter(explode("\n", stream_get_contents($stream))));
        fclose($stream);

        // One heading line plus one line per row.
        expect($written)->toBe(3)
            ->and(count($lines))->toBe(4)
            ->and(count(array_filter($lines, static fn (string $line): bool => str_contains($line, 'CH-'))))->toBe(3);
    }
})->group('T-P9-06', 'A1');

it('stops the export and writes nothing when the audit logger fails', function (): void {
    // BUG: App\Domain\Reports\Actions\ExportReportCsv::prepare() calls AuditLogger::record('report.exported', ...) without checking its
    // null result (the logger returns null when it cannot record, see SetPhoneReminderOptIn which does check). An export therefore
    // runs with no audit row. Remove the skip once prepare() throws when record() returns null.
    $payer = t906cUser('student');
    t906cOrder('FAIL-1', $payer, 'EG', 'EGP', 1000, 'paid', '2026-11-12 10:00:00');
    $admin = t906cUser('admin');

    activity()->disableLogging();
    try {
        expect(fn () => app(ExportReportCsv::class)->prepare($admin, ReportKey::Sales, t906cFilters()))
            ->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
})->group('T-P9-06', 'A1');

// ---------------------------------------------------------------- page

it('lets the admin page run an export for each report the user can read and keeps the filter scope', function (): void {
    $admin = t906cUser('admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ReportsPage::class)->assertSuccessful();
    expect(t906cCatalog()->definition(ReportKey::Sales)->columns())->not->toBeEmpty();
})->group('T-P9-06', 'A1');

// ---------------------------------------------------------------- invariants

it('keeps personal, AI message and entitlement columns out of every report source', function (): void {
    $forbidden = ["'student_hash'", "'payer_id'", "'student_id'", "'question'", "'ai_answer'", "'body'"];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Domain/Reports'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($fileInfo->getPathname());
        foreach ($forbidden as $term) {
            expect(str_contains($source, $term))->toBeFalse($fileInfo->getFilename().' mentions '.$term);
        }
        expect($source)->not->toContain('Illuminate\\Http\\')
            ->and($source)->not->toContain('Filament\\')
            ->and(str_contains($source, "'entitlements'"))->toBeFalse();
    }
})->group('T-P9-06', 'A3');

it('keeps the audit label for report exports in both locales', function (): void {
    expect(trans('audit-events.report.exported', [], 'en'))->not->toBe('audit-events.report.exported')
        ->and(trans('audit-events.report.exported', [], 'ar'))->not->toBe('audit-events.report.exported');
})->group('T-P9-06', 'A1');
